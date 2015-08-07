<?php

namespace App\Model;

use Nette;

class MessageManager extends Nette\Object
{
	const
		TABLE_NAME = 'messages',
		COLUMN_ID = 'id',
		COLUMN_RECIPIENT_ID = 'recipient_id',
		COLUMN_SENDER_ID = 'sender_id',
		COLUMN_CONTENT = 'content',
		COLUMN_NICKNAME = 'nickname',
		COLUMN_EMAIL = 'email',
		COLUMN_SEX = 'sex',
		COLUMN_AGE = 'age',
		COLUMN_APPROVED = 'approved',
		COLUMN_SHOWED = 'showed',
		COLUMN_CREATED_AT = 'created_at',
		COLUMN_IP = 'ip';
	
	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}

	public function add($userId, $values, $identity) {
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
				'recipient_id' => $userId,
				'nickname' => $identity ? $identity->data['nickname'] : $values->nickname,
				'email' => $identity ? $identity->data['email'] : $values->email,
				'sex' => $identity ? $identity->data['sex'] : $values->sex,
				'age' => $identity ? $identity->data['age'] : $values->age,
				'content' => $values->content,
				'sender_id' => $identity ? $identity->data['id'] : NULL,
		));
		// TODO úpravy vzkazů pro přihlášené (?)
	}
	
	public function getPageByUser($paginator, $page, $resultsPerPage, $user) {
		$messages = $this->database->table(self::TABLE_NAME)->where('recipient_id', $user->id);

		$paginator->setItemCount($messages->count());
		$paginator->setItemsPerPage($resultsPerPage);
		$paginator->setPage($page ?: 1);
	
		return $messages->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
	}

}