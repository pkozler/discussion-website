<?php

namespace App\Presenters;

use App\Model\DuplicateNameException;
use App\Model\IdentityInUseException;
use App\Model\InvalidTokenException;
use App\Model\ResendRejectedException;
use Nette,
	App\Model\UserManager,
	App\Model\MessageManager,
	Nette\Utils\Paginator,
	Nette\Application\UI\Form;

class UserPresenter extends BasePresenter
{
	/** @var UserManager @inject */
	public $userManager;
	
	/** @var MessageManager @inject */
	public $messageManager;

	public function renderDefault($page)
	{
		$paginator = new Paginator;

		$this->template->users = $this->userManager->getPage($paginator, $page, parent::RESULTS_PER_PAGE);

		$this->template->paginator = $paginator;
	}

	public function renderShow($userId, $page) {
		$user = $this->userManager->getProfile($userId);

		if (!$user) {
			$this->error('Stránka nebyla nalezena.');
		}
		
		$this->template->profile = $user;
		$this->template->id = $userId;

		$paginator = new Paginator;
		
		$this->template->messages = $this->messageManager->getPageByUser($paginator, $page, parent::RESULTS_PER_PAGE, $user);

		$this->template->paginator = $paginator;
	}
	
	public function createComponentSignUpForm() {
		$form = new Form;
	
		$form->addText('nickname', 'Nickname:')
		->addRule(Form::MAX_LENGTH, 'Přezdívka je příliš dlouhá.', parent::MAX_NAME_LENGTH)
		->addRule(Form::PATTERN,
				'Jsou povoleny pouze kombinace znaků a-ž, A-Ž, 0-9, pomlčky, podtržítka a mezery',
				parent::NICKNAME_PATTERN)
				->setRequired('Prosím vyplňte přezdívku.');
	
		$form->addText('email', 'E-mail:')
		->addRule(Form::EMAIL, 'E-mailová adresa je neplatná.')
		->setRequired('Prosím vyplňte svoji e-mailovou adresu.')
		->setDefaultValue('@')
		->setAttribute('type', 'email');
	
		$form->addText('age', 'Věk:')
		->addRule(Form::INTEGER, 'Věk musí být číslo.')
		->addRule(Form::RANGE, 'Neplatný věk.', array(parent::MIN_AGE, parent::MAX_AGE))
		->setRequired('Prosím uveďte věk.')
		->setAttribute('type', 'number');
	
		$gender = array(
				'male' => 'muž',
				'female' => 'žena',
		);
	
		$form->addRadioList('gender', 'Pohlaví:', $gender)
		->setRequired('Prosím uveďte pohlaví.')
		->getSeparatorPrototype()->setName(NULL);
	
		$form->addPassword('password', 'Heslo:')
		->addRule(Form::MIN_LENGTH, 'Heslo musí mít minimálně %d znaků.', parent::MAX_PASSWORD_LENGTH)
		->setRequired('Prosím vyplňte své heslo.');
	
		$form->addPassword('passwordVerify', 'Heslo znovu:')
		->addRule(Form::EQUAL, 'Hesla se neshodují', $form['password'])
		->setRequired('Prosím potvrďte své heslo.');
	
		$form->addSubmit('send', 'Vytvořit účet');
	
		$form->onSuccess[] = array($this, 'signUpFormSucceeded');
	
		return $this->bootstrapFormRender($form);
	}
	
	public function signUpFormSucceeded($form, $values) {
		try {
			$this->addIpAddress($values);
			$this->userManager->add($values);
			$token = $this->userManager->createToken($values->email, TRUE);
			$url = $this->link('//User:activate', array('email' => $values->email, 'token' => $token));
			$path = __DIR__ . '/templates/User/email.latte';
			$this->userManager->sendMail($values->email, $url, $path);
			$this->flashMessage('Registrace proběhla úspěšně.
					        Na uvedenou e-mailovou adresu byl zaslán aktivační odkaz, po jehož otevření
        					bude možné začít účet využívat. Platnost aktivačního odkazu je 1 den.
					        Pokud Vám žádný e-mail nedorazil (nebo platnost odkazu již vypršela),
					        můžete se pokusit o přihlášení a nechat si ho zaslat znovu.', 'success');
		} catch(DuplicateNameException $e){
			$form->addError("Zadaná e-mailová adresa nebo přezdívka již zřejmě existuje, prosím zkuste jiné.");
		}
	}
	
	public function createComponentMessageForm()
	{
		$form = new Form;

		$nicknameInput = $form->addText('nickname', 'Nickname:');
		$nicknameInput->addRule(Form::MAX_LENGTH, 'Přezdívka je příliš dlouhá.', parent::MAX_NAME_LENGTH)
		->addCondition(Form::FILLED, TRUE)
			->addRule(Form::PATTERN,
				'Jsou povoleny pouze kombinace znaků a-ž, A-Ž, 0-9, pomlčky, podtržítka a mezery',
				parent::NICKNAME_PATTERN);
		
		if ($this->getUser()->isLoggedIn()) {
			$nicknameInput->setDisabled()->setDefaultValue($this->getUser()->getIdentity()->data['nickname']);
		}
		
		$form->addTextArea('content', 'Obsah:')
		->setAttribute('rows', 10)
		->addRule(Form::MAX_LENGTH, 'Text je příliš dlouhý.', parent::MAX_TEXT_LENGTH)
		->setRequired();
		 
		$emailInput = $form->addText('email', 'E-mail:');
		
		$emailInput->setAttribute('pattern', '.*@.*')
		->setDefaultValue('@')
		->addCondition(Form::PATTERN, '@?')
			->elseCondition()
				->addRule(Form::EMAIL, 'E-mailová adresa je neplatná.');
		
		if ($this->getUser()->isLoggedIn()) {
			$emailInput->setDisabled()->setDefaultValue($this->getUser()->getIdentity()->data['email']);
		}
		
		$ageInput = $form->addText('age', 'Věk:');
		
		$ageInput->setAttribute('type', 'number')
		->setAttribute('min', parent::MIN_AGE)
		->setAttribute('max', parent::MAX_AGE)
		->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER, 'Věk musí být číslo.')
			->addRule(Form::RANGE, 'Neplatný věk.', array(parent::MIN_AGE, parent::MAX_AGE));
		
		if ($this->getUser()->isLoggedIn()) {
			$ageInput->setDisabled()->setDefaultValue($this->getUser()->getIdentity()->data['age']);
		}
		
		$gender = array(
				'male' => 'muž',
				'female' => 'žena',
		);
		$genderInput = $form->addSelect('gender', 'Pohlaví:', $gender);
		
		$genderInput->setPrompt('');
		
		if ($this->getUser()->isLoggedIn()) {
			$genderInput->setDisabled()->setDefaultValue($this->getUser()->getIdentity()->data['gender']);
		}

		$form->addSubmit('send', 'Vložit vzkaz');
		$form->onSuccess[] = array($this, 'messageFormSucceeded');

		return $this->bootstrapFormRender($form);
	}
	
	public function messageFormSucceeded($form, $values)
	{
		$userId = $this->getParameter('userId');

		$this->emptyToNull($values);
		$this->addIpAddress($values);

		try {
			$this->messageManager->add($userId, $values,
				$this->getUser()->isLoggedIn() ? $this->getUser()->getIdentity() : NULL);

			$this->flashMessage('Vzkaz byl úspěšně vložen.', 'success');
			$this->redirect('this');
		}
		catch (IdentityInUseException $e) {
			$form->addError($e->getMessage());
		}
	}

	/** @persistent */
	public $email;

	/** @persistent */
	public $token;

	public function actionRecover($email, $token) {
		$this->email = $email;
		$this->token = $token;
	}

	/**
	 * Změní heslo.
	 * @param unknown $form formulář změny hesla
	 */
	public function changePasswordFormSucceeded($form, $values) {
		try {
			$this->userManager->changePassword($this->user->getId(),
				$values->oldPassword, $values->password);
			$this->flashMessage('Heslo bylo změněno.', 'success');
		}
		catch (Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage(), 'error');
		}

		$this->email = NULL;
		$this->token = NULL;
	}

	public function actionPassword($id) {
		if (!($this->user->isLoggedIn() && $this->user->getId() == $id)) {
			$this->redirect('User:show', array($id));
		}
	}

	/**
	 * Vytvoří formulář změny hesla.
	 */
	public function createComponentChangePasswordForm() {
		$form = new Form;

		$form->addPassword('oldPassword', 'Původní heslo:')
			->setRequired('Prosím vyplňte své heslo.');

		$form->addPassword('password', 'Nové heslo:')
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít minimálně %d znaků.', 8)
			->setRequired('Prosím vyplňte nové heslo.');

		$form->addPassword('passwordVerify', 'Heslo znovu:')
			->addRule(Form::EQUAL, 'Hesla se neshodují', $form['password'])
			->setRequired('Prosím potvrďte nové heslo.');

		$form->addSubmit('send', 'Změnit heslo');

		$form->onSuccess[] = array($this, 'changePasswordFormSucceeded');

		return $this->bootstrapFormRender($form);
	}

	/**
	 * Změní zapomenuté heslo.
	 * @param unknown $form formulář změny hesla
	 */
	public function recoverPasswordFormSucceeded($form, $values) {
		try {
			if ($form->submitted->getValue() === 'Změnit heslo') {
				$this->userManager->recoverPassword($this->email, $this->token, $values->password);
				$this->flashMessage('Heslo bylo změněno.', 'success');
			}
			else {
				$this->userManager->recoverPassword($this->email, $this->token);
				$this->flashMessage('Žádost o změnu hesla byla zrušena.', 'success');
			}
		}
		catch (InvalidTokenException $e) {
			$this->flashMessage('Neplatný odkaz.', 'error');
			$this->redirect('Homepage:default');
		}

		$this->email = NULL;
		$this->token = NULL;
	}

	/**
	 * Vytvoří formulář obnovy hesla.
	 */
	public function createComponentRecoverPasswordForm() {
		$form = new Form;

		$form->addPassword('password', 'Heslo:')
		->addRule(Form::MIN_LENGTH, 'Heslo musí mít minimálně %d znaků.', 8)
		->setRequired('Prosím vyplňte nové heslo.');
		
		$form->addPassword('passwordVerify', 'Heslo znovu:')
		->addRule(Form::EQUAL, 'Hesla se neshodují', $form['password'])
		->setRequired('Prosím potvrďte nové heslo.');

		$form->addSubmit('send', 'Změnit heslo');
		$form->addSubmit('cancel', 'Zrušit změnu hesla')->setValidationScope(FALSE);

		$form->onSuccess[] = array($this, 'recoverPasswordFormSucceeded');

		return $this->bootstrapFormRender($form);
	}
	
	/**
	 * Aktivuje účet.
	 * @param unknown $email e-mailová adresa
	 * @param unknown $token aktivační kód
	 */
	public function actionActivate($email, $token) {
		try {
			$this->userManager->activate($email, $token);
			$this->flashMessage('Váš účet byl úspěšně aktivován, nyní se můžete přihlásit.', 'success');
		} catch(InvalidTokenException $e){
			$this->flashMessage("Neplatný token.", 'error');
		}
		
		$this->redirect('Sign:in');
	}
	
	/**
	 * Opětovně odešle aktivační odkaz.
	 */
	public function actionResend($email) {
		try {
			$token = $this->userManager->createToken($email);
			$url = $this->link('//User:activate', array('email' => $email, 'token' => $token));
			$path = __DIR__ . '/templates/User/email.latte';
			$this->userManager->sendMail($email, $url, $path);
			$this->flashMessage("Aktivační odkaz byl odeslán na e-mailovou adresu uvedenou při registraci účtu.", 'success');
		} catch (ResendRejectedException $e) {
			$this->flashMessage("Aktivační odkaz byl odeslán před méně než 1 hodinou. Zkontrolujte, zda nebyl označen jako spam
	                		nebo zda nemáte špatně nastavený e-mailový server. V opačném případě chvíli počkejte a poté
	                		si ho nechte zaslat znovu.", 'error');
		}
		
		$this->redirect('Sign:in');
	}

}