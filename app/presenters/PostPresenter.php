<?php
namespace App\Presenters;

use App\Model\IdentityInUseException;
use Nette,
    Nette\Application\UI\Form,
	Nette\Utils\Paginator,
	App\Model\PostManager,
	App\Model\CommentManager;
use Tracy\Debugger;

class PostPresenter extends BasePresenter
{

    /** @var PostManager @inject */
    public $postManager;
    
    /** @var CommentManager @inject */
    public $commentManager;

    public function actionVote($vote, $userId, $postId, $commentId = NULL) {
		if ($this->user->isLoggedIn() && $this->user->getId() != $userId) {
			if ($commentId) {
				try {
					$like = $this->commentManager->vote($vote, $this->user->getId(), $postId, $commentId);
					$this->flashMessage('Komentáři byl přičten ' . ($like ? 'kladný' : 'záporný') . ' bod.', 'success');
				} catch (App\Model\DuplicateCommentVoteException $e) {
					$this->flashMessage('Tento komentář jste již obodovali.', 'error');
				} catch (App\Model\InvalidCommentIdException $e) {
					$this->flashMessage('Neplatné ID uživatele nebo komentáře.', 'error');
				}
			} else {
				try {
					$like = $this->postManager->vote($vote, $this->user->getId(), $postId);
					$this->flashMessage('Příspěvku byl přičten ' . ($like ? 'kladný' : 'záporný') . ' bod.', 'success');
				} catch (App\Model\DuplicatePostVoteException $e) {
					$this->flashMessage('Tento příspěvek jste již obodovali.', 'error');
				} catch (App\Model\InvalidPostIdException $e) {
					$this->flashMessage('Neplatné ID uživatele nebo příspěvku.', 'error');
				}
			}
		}

		$this->redirect('show', $postId);
    }

    public function renderShow($postId, $page)
    {
        $post = $this->postManager->getById($postId);
        
        if (!$post) {
        	$this->error('Stránka nebyla nalezena.');
        }
        
        $this->template->post = $post;
        $this->template->id = $postId;
        
        $topComments = $this->commentManager->getTopByPost($post);
        
        $this->template->bestComment = $topComments['best'];
        $this->template->worstComment = $topComments['worst'];
        $this->template->controversialComment = $topComments['controversial'];

        $paginator = new Paginator;
        
        $this->template->comments = $this->commentManager->getPageByPost($paginator, $page, parent::RESULTS_PER_PAGE, $post);

        $this->template->paginator = $paginator;
    }

	public function postFormSucceeded($form, $values)
	{
	    $postId = $this->getParameter('postId');

	    $this->emptyToNull($values);
	    $this->addIpAddress($values);

		try {
			$this->postManager->add($values,
				$this->user->isLoggedIn() ? $this->user->getIdentity() : NULL);

			$this->flashMessage('Příspěvek byl úspěšně publikován.', 'success');
			$this->redirect('Homepage:default');
		}
		catch (IdentityInUseException $e) {
			$form->addError($e->getMessage());
		}
	}

	protected function createComponentPostForm()
	{
		return self::bootstrapFormRender(
			$this->postCommentForm(TRUE));
	}

	public function commentFormSucceeded($form, $values)
	{
		$postId = $this->getParameter('postId');

		$this->emptyToNull($values);
		$this->addIpAddress($values);

		try {
			$this->commentManager->add($postId, $values,
				$this->user->isLoggedIn() ? $this->user->getIdentity() : NULL);

			$this->flashMessage('Komentář byl úspěšně vložen.', 'success');
			$this->redirect('this');
		}
		catch (IdentityInUseException $e) {
			$form->addError($e->getMessage());
		}
	}

	protected function createComponentCommentForm()
	{
		return self::bootstrapFormRender(
			$this->postCommentForm(FALSE));
	}
	
	private function postCommentForm($post = FALSE) {
		$form = new Form;

		if ($post) {
			$form->addText('title', 'Název:')
			->addRule(Form::MAX_LENGTH, 'Název je příliš dlouhý.', parent::MAX_NAME_LENGTH)
			->setRequired();
			
			$cathegories = $this->postManager->getAllCategories()->fetchPairs('id', 'description');
			$form->addSelect('category', 'Kategorie:', $cathegories)
			->setRequired()
			->setPrompt('--- Vyberte kategorii ---'); // nastavit výchozí
		}
		
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
		->setAttribute('rows', $post ? 20 : 10)
		->addRule(Form::MAX_LENGTH, 'Text je příliš dlouhý.', parent::MAX_TEXT_LENGTH)
		->setRequired();
		 
		$emailInput = $form->addText('email', 'E-mail:');
		
		$emailInput->setAttribute('pattern', '.*@.*') // pokud je pole vyplněné, musí obsahovat zavináč
		->setDefaultValue('@')
		->addCondition(Form::PATTERN, '@?') // byla odeslána prázdná hodnota nebo samotný zavináč
			->elseCondition() // v opačném případě se nastaví validační pravidlo
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
		
		if ($post) {
			$form->addSubmit('send', 'Vložit příspěvek');
			$form->onSuccess[] = array($this, 'postFormSucceeded');
		}
		else {
			$form->addSubmit('send', 'Vložit komentář');
			$form->onSuccess[] = array($this, 'commentFormSucceeded');
		}

		return $form;
	}

}