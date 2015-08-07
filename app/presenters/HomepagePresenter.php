<?php

namespace App\Presenters;

use Nette,
	App\Model,
	Nette\Application\UI\Form,
	Nette\Utils\Paginator,
	App\Model\PostManager,
	Nette\Database\SqlLiteral;

use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{

	/** @var Nette\Database\Context */
	private $database;
	
	/** @var PostManager @inject */
	public $postManager;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}

	public function createComponentSearchForm() {
		$httpRequest = $this->context->getByType('Nette\Http\Request');

		$form = new Form;
	
		$form->setMethod('GET');

		$form->setAction($this->link('Homepage:search'));

		$form->addText('search', 'Vyhledat:')->setDefaultValue($httpRequest->getQuery('search'));

		$form->addSubmit('send', 'Přihlásit se');
	
		$form->onSuccess[] = array($this, 'signInFormSucceeded');
		
		return $this->bootstrapFormRender($form);
	}

	public function actionSearch($search) {
		if ($search === '') {
			$this->redirect('Homepage:default');
		}
	}

	public function renderSearch($search) {
		$this->template->categories = $this->postManager->getAllCategories();

		// TODO - předělat (použít skládání dotazů, zprovoznit vyhledávání přes více tabulek, udělat stránkování)
		$results = $this->database->query(
			'SELECT id, NULL AS post_id, NULL AS recipient_id, title, content FROM posts
				WHERE content LIKE ? LIMIT ' . parent::RESULTS_PER_PAGE . ' UNION
			SELECT id, post_id, NULL AS recipient_id, NULL AS title, content FROM comments
				WHERE content LIKE ? LIMIT ' . parent::RESULTS_PER_PAGE . ' UNION
			SELECT id, NULL AS post_id, recipient_id, NULL AS title, content FROM messages
				WHERE content LIKE ? LIMIT ' . parent::RESULTS_PER_PAGE, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'
		)->fetchAll();

		$this->template->searched = $search;		

		$this->template->results = $results;
	}
	
	public function renderDefault($category, $order, $page)	{
		$this->template->categories = $this->postManager->getAllCategories();
		
		$category = $this->postManager->getCategoryByName($category);

		$this->template->currentCategory = $category;
		
		$this->template->currentOrder = $order;
		
		$paginator = new Paginator;

		$this->template->posts = $this->postManager->getPage($paginator, $page, parent::RESULTS_PER_PAGE, $category, $order);

		$this->template->paginator = $paginator;
	}

}
