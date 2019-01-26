<?php

namespace App\Model;

use Nette;
use Tracy\Debugger;
use Nette\Database\SqlLiteral;
use Nette\Utils\Paginator;

class PostManager extends Nette\Object
{
	const
		TABLE_NAME = 'posts',
		COLUMN_ID = 'id',
		COLUMN_CATEGORY_ID = 'category_id',
		COLUMN_USER_ID = 'user_id',
		COLUMN_TITLE = 'title',
		COLUMN_CONTENT = 'content',
		COLUMN_NICKNAME = 'nickname',
		COLUMN_EMAIL = 'email',
		COLUMN_GENDER = 'gender',
		COLUMN_AGE = 'age',
		COLUMN_APPROVED = 'approved',
		COLUMN_SHOWED = 'showed',
		COLUMN_CREATED_AT = 'created_at',
		COLUMN_IP = 'ip',
		LIKE = 'like',
		HATE = 'hate';

	/** @var Nette\Database\Context */
	private $database;
	
	private $postSql;
	
	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
		$this->postSql = new SqlLiteral("posts.*, 
				IF (CHAR_LENGTH(posts.content) > 100, CONCAT(LEFT(posts.content, 100), ' [...]'), posts.content)
				 AS snippet, 
				 (SELECT COALESCE(
				SUM(
				      CASE
				        WHEN `post_votes`.`vote` = ?
				        THEN 1
				        WHEN `post_votes`.`vote` = ?
				        THEN -1
				        ELSE 0
				      END
    			), 0) FROM `post_votes` WHERE (`post_votes`.`post_id` = `posts`.`id`)) AS score,
				LEAST((SELECT count(`post_votes`.`post_id`) FROM `post_votes` WHERE (`post_votes`.`post_id` = `posts`.`id`)
				 AND (`post_votes`.`vote` = ?)), 
				(SELECT count(`post_votes`.`post_id`) FROM `post_votes` WHERE (`post_votes`.`post_id` = `posts`.`id`)
				 AND (`post_votes`.`vote` = ?))) AS `controversion`,
				COUNT(:comments.id) AS replies");
	}

	public function getPage($paginator, $page, $resultsPerPage, $category, $order) {
		$posts = $this->database->table(self::TABLE_NAME)
		->select($this->postSql, self::LIKE, self::HATE, self::LIKE, self::HATE)->group('posts.id');
                
		if ($category) {
			$posts = $posts->where(self::COLUMN_CATEGORY_ID, $category->id);
		}

		$column = NULL;
		
		switch ($order) {
			case 'views': {
				$column = self::COLUMN_CREATED_AT;
				break;
			}
			case 'comments': {
				$column = 'replies DESC';
				break;
			}
			case 'likes': {
				$column = 'score DESC';
				break;
			}
			case 'hates': {
				$column = 'score ASC';
				break;
			}
			case 'controversions': {
				$column = 'controversion DESC';
				break;
			}
			default: {
				$column = self::COLUMN_CREATED_AT;
				break;
			}
		}
			
		$posts = $posts->order($column);

		$paginator->setItemCount($posts->count());
		$paginator->setItemsPerPage($resultsPerPage);
		$paginator->setPage($page ?: 1);

		return $posts->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
	}
	
	public function getAllCategories() {
		return $this->database->table('categories')->order('id');
	}
	
	public function getCategoryByName($name) {
		return $this->database->table('categories')->select('*')->where('name', $name)->fetch();
	}
	
	public function getById($id) {
		return $this->database->table(self::TABLE_NAME)
		->select($this->postSql, self::LIKE, self::HATE, self::LIKE, self::HATE)->group('posts.id')->get($id);
	}
	
	public function add($values, $identity) {
		// kontrola pro neregistrované uživatele
		if (!$identity) {
			// kontrola, zda zadaná přezdívka není použitá
			if ($values->nickname) {
				$nickExists = $this->database->table(self::TABLE_NAME)->where(
					self::COLUMN_NICKNAME . ' = ?', $values->nickname
				)->fetch();

				if ($nickExists) {
					throw new IdentityInUseException('Zadanou přezdívku již používá jiný registrovaný uživatel.');
				}
			}

			// kontrola, zda zadaná adresa není použitá
			if ($values->email) {
				$emailExists = $this->database->table(self::TABLE_NAME)->where(
					self::COLUMN_EMAIL . ' = ?', $values->email
				)->fetch();

				if ($emailExists) {
					throw new IdentityInUseException('Zadanou e-mailovou adresu již používá jiný registrovaný uživatel.');
				}
			}
		}

		$this->database->table(self::TABLE_NAME)->insert(array(
				self::COLUMN_CATEGORY_ID => $values->category,
				self::COLUMN_TITLE => $values->title,
				self::COLUMN_NICKNAME => $identity ? $identity->data['nickname'] : $values->nickname,
				self::COLUMN_EMAIL => $identity ? $identity->data['email'] : $values->email,
				self::COLUMN_GENDER => $identity ? $identity->data['gender'] : $values->gender,
				self::COLUMN_AGE => $identity ? $identity->data['age'] : $values->age,
				self::COLUMN_CONTENT => $values->content,
				self::COLUMN_USER_ID => $identity ? $identity->data['id'] : NULL,
		)); // TODO editace příspěvků pro přihlášené
	}
	
	public function vote($vote, $userId, $id) {
		if ($vote === self::LIKE || $vote === self::HATE) {
			try {
				$this->database->table('post_votes')->insert(array(
						'user_id' => $userId,
						'post_id' => $id,
						'vote' => $vote,
				));
				
				return $vote === self::LIKE;
			}
			catch (Nette\Database\UniqueConstraintViolationException $e) {
				throw new DuplicatePostVoteException;
			}
			catch (Nette\Database\ForeignKeyConstraintViolationException $e) {
				throw new InvalidPostIdException;
			}
		}
	}
	
}

class DuplicatePostVoteException extends \Exception
{}

class InvalidPostIdException extends \Exception
{}

class IdentityInUseException extends \Exception
{}