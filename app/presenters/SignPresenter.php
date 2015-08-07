<?php

namespace App\Presenters;

use Nette,
	App\Model\UserManager,
	Nette\Application\UI\Form;

/**
 * Sign in/out presenters.
 */
class SignPresenter extends BasePresenter
{
	/** @var UserManager @inject */
	public $userManager;
	
	/**
	 * Vypíše dodatečná data do šablony přihlašovací stránky.
	 * @param string $email e-mailová adresa
	 */
	public function renderIn($email = NULL)
	{
		$this->template->email = $email;
	}
	
	public function createComponentSignInForm() {
		$form = new Form;
	
		$form->addText('email', 'Nickname/e-mail:')
		->setRequired('Prosím vyplňte svůj nickname nebo e-mailovou adresu.');
	
		$form->addPassword('password', 'Heslo:')
		->addRule(Form::MIN_LENGTH, 'Heslo musí mít minimálně %d znaků.', parent::MAX_PASSWORD_LENGTH)
		->setRequired('Prosím vyplňte své heslo.');
	
		$form->addCheckbox('remember', 'Pamatovat si mě');
	
		$form->addSubmit('send', 'Přihlásit se');
	
		$form->onSuccess[] = array($this, 'signInFormSucceeded');
		
		return $this->bootstrapFormRender($form);
	}
	
	public function signInFormSucceeded($form, $values) {
		try {
			if ($values->remember) {
				$this->user->setExpiration('14 days', FALSE);
			} else {
				$this->user->setExpiration('20 minutes', TRUE);
			}
			$this->user->login($values->email, $values->password);
			$this->flashMessage('Přihlášení proběhlo úspěšně.', 'success');
			$this->redirect('Homepage:default');
		}
		catch (Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage(), 'error');
		}
		catch (App\Model\InactiveAccountException $e) {
			$this->redirect('this', array('email' => $e->getAccountEmail()));
		}
	}
	
	public function createComponentForgotPasswordForm() {
		$form = new Form;
	
		$form->addText('email', 'E-mail:')
		->addRule(Form::EMAIL, 'E-mailová adresa má neplatný formát.')
		->setRequired('Prosím vyplňte svoji e-mailovou adresu.')
		->setDefaultValue('@')
		->setAttribute('type', 'email');
	
		$form->addSubmit('send', 'Odeslat');
	
		$form->onSuccess[] = array($this, 'forgotPasswordFormSucceeded');
	
		return $this->bootstrapFormRender($form);
	}
	
	public function forgotPasswordFormSucceeded($form, $values) {
		try {
			$this->userManager->verifyEmail($values->email);
			$token = $this->userManager->createToken($values->email);
			$url = $this->link('//User:recover', array('email' => $values->email, 'token' => $token));
			$path = __DIR__ . '/templates/Sign/email.latte';
			$this->userManager->sendMail($values->email, $url, $path);
			$this->flashMessage("Odkaz pro změnu hesla byl odeslán na e-mailovou adresu uvedenou při registraci účtu.", 'success');
			$this->redirect('Homepage:default');
		}
		catch (Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
		catch (Nette\Security\InactiveAccountException $e) {
			$form->addError('Účet se zadanou e-mailovou adresou nebyl aktivován.');
		}
		catch (App\Model\ResendRejectedException $e) {
			$form->addError("Odkaz pro změnu hesla byl odeslán před méně než 1 hodinou. Zkontrolujte, zda nebyl označen jako spam
                		nebo zda nemáte špatně nastavený e-mailový server. V opačném případě chvíli počkejte a poté
                		si ho nechte zaslat znovu.");
		}
	}

	/**
	 * Odhlásí uživatele.
	 */
	public function actionOut()
	{
	    $this->getUser()->logout();
	    $this->flashMessage('Odhlášení bylo úspěšné.', 'success');
	    $this->redirect('Homepage:');
	}

}
