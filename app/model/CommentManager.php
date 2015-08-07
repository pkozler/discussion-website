<?php

namespace App\Model;

use Nette,
	Nette\Database\SqlLiteral;

class CommentManager extends Nette\Object
{
	const
		TABLE_NAME = 'comments',
		COLUMN_ID = 'id',
		COLUMN_POST_ID = 'post_id',
		COLUMN_USER_ID = 'user_id',
		COLUMN_PARENT_ID = 'parent_id',
		COLUMN_CONTENT = 'content',
		COLUMN_NICKNAME = 'nickname',
		COLUMN_EMAIL = 'email',
		COLUMN_SEX = 'sex',
		COLUMN_AGE = 'age',
		COLUMN_APPROVED = 'approved',
		COLUMN_SHOWED = 'showed',
		COLUMN_CREATED_AT = 'created_at',
		COLUMN_IP = 'ip',
		LIKE = 'like',
		HATE = 'hate';
		
	/** @var Nette\Database\Context */
	private $database;
	
	private $commentSql;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
		
		$this->commentSql = new SqlLiteral("comments.*, 
                        (SELECT COALESCE(
                           SUM(
                                 CASE
                                   WHEN `comment_votes`.`vote` = ?
                                   THEN 1
                                   WHEN `comment_votes`.`vote` = ?
                                   THEN -1
                                   ELSE 0
                                 END
                               ), 0) FROM `comment_votes` WHERE (`comment_votes`.`post_id` = `comments`.`post_id`)
    			 AND (`comment_votes`.`comment_id` = `comments`.`id`)) AS score,
				LEAST((SELECT count(`comment_votes`.`comment_id`) FROM `comment_votes` WHERE
				 (`comment_votes`.`post_id` = `comments`.`post_id`)
				 AND (`comment_votes`.`comment_id` = `comments`.`id`)
				 AND (`comment_votes`.`vote` = ?)), 
				(SELECT count(`comment_votes`.`comment_id`) FROM `comment_votes` WHERE
				 (`comment_votes`.`post_id` = `comments`.`post_id`)
				 AND (`comment_votes`.`comment_id` = `comments`.`id`)
				 AND (`comment_votes`.`vote` = ?))) AS `controversion`");
	}
	
	public function add($postId, $values, $identity) {
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
				self::COLUMN_POST_ID => $postId,
				self::COLUMN_NICKNAME => $identity ? $identity->data['nickname'] : $values->nickname,
				self::COLUMN_EMAIL => $identity ? $identity->data['email'] : $values->email,
				self::COLUMN_SEX => $identity ? $identity->data['sex'] : $values->sex,
				self::COLUMN_AGE => $identity ? $identity->data['age'] : $values->age,
				self::COLUMN_CONTENT => $values->content,
				self::COLUMN_USER_ID => $identity ? $identity->data['id'] : NULL,
		)); // TODO úpravy komentářů pro přihlášené
	}
	
	public function getPageByPost($paginator, $page, $resultsPerPage, $post) {
		$comments = $post->related(self::TABLE_NAME)->select($this->commentSql, self::LIKE, self::HATE, self::LIKE, self::HATE)
		->order(self::COLUMN_CREATED_AT . ' DESC');
		
		$paginator->setItemCount($comments->count());
		$paginator->setItemsPerPage($resultsPerPage);
		$paginator->setPage($page ?: 1);

		return $comments->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
	}
	
	public function getTopByPost($post) {
		$return = array();

		$return['best'] = $post->related(self::TABLE_NAME)->select($this->commentSql, self::LIKE, self::HATE, self::LIKE, self::HATE)
		->order('score DESC')->limit(1)->fetch();
	
		$return['worst'] = $post->related(self::TABLE_NAME)->select($this->commentSql, self::LIKE, self::HATE, self::LIKE, self::HATE)
		->order('score ASC')->limit(1)->fetch();
	
		$return['controversial'] = $post->related(self::TABLE_NAME)->select($this->commentSql, self::LIKE, self::HATE, self::LIKE, self::HATE)
		->order('controversion DESC')->limit(1)->fetch();

		$return['best'] = ($return['best'] !== FALSE && 
			$return['best']->score > 0) ? $return['best'] : NULL;
		$return['worst'] = ($return['worst'] !== FALSE && 
			$return['worst']->score < 0) ? $return['worst'] : NULL;
		$return['controversial'] = ($return['controversial'] !== FALSE && 
			$return['controversial']->controversion > 0) ? $return['controversial'] : NULL;
	
		return $return;
	}
	
	public function like($vote, $userId, $postId, $id) {
		if ($vote === self::LIKE || $vote === self::HATE) {
			try {
				$this->database->table('comment_votes')->insert(array(
						'user_id' => $userId,
						'post_id' => $postId,
						'comment_id' => $id,
						'vote' => $vote,
				));
				
				return $vote === self::LIKE;
			}
			catch (Nette\Database\UniqueConstraintViolationException $e) {
				throw new DuplicateCommentVoteException;
			}
			catch (Nette\Database\ForeignKeyConstraintViolationException $e) {
				throw new InvalidCommentIdException;
			}
		}
	}

}

class DuplicateCommentVoteException extends \Exception
{}

class InvalidCommentIdException extends \Exception
{}