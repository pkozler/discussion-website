<?php

namespace App\Model;

use Nette,
	Nette\Security\Passwords,
	Nette\Mail\Message,
	Nette\Mail\SendmailMailer,
	Latte\Engine,
	Nette\Utils\DateTime,
	Tracy\Debugger,
	Nette\Database\SqlLiteral;

/**
 * Users management.
 */
class UserManager extends Nette\Object implements Nette\Security\IAuthenticator
{
	const
		TABLE_NAME = 'users',
		COLUMN_ID = 'id',
		COLUMN_IP_ID = 'ip_id',
		COLUMN_PHOTO_ID = 'photo_id',
		COLUMN_EMAIL = 'email',
		COLUMN_PASSWORD_HASH = 'password',
		COLUMN_PASSWORD_TOKEN_HASH = 'password_token',
		COLUMN_PASSWORD_TOKEN_HASH_VALIDITY = 'password_token_validity',
		COLUMN_NAME = 'nickname',
		COLUMN_SEX = 'sex',
		COLUMN_BIRTHDATE = 'birthdate',
		COLUMN_ACTIVATED = 'activated',
		COLUMN_TOKEN_RESEND_BLOCKED = 'token_resend_blocked',
		COLUMN_ROLE = 'role',
		
		VIEW_USER_IDENTITIES = 'identities',
		COLUMN_AGE = 'age';

	/** @var Nette\Database\Context */
	private $database;

	private $userSql;

	private $profileSql;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
		$this->userSql = new SqlLiteral("users.*, 
			COUNT(DISTINCT :posts.id) AS `post_count`, 
			COUNT(DISTINCT :comments.id) AS `comment_count`, 
			COUNT(DISTINCT :messages.id) AS `received_message_count`, 
			(SELECT COUNT(`messages`.`id`) FROM `messages` WHERE `users`.`id` = `messages`.`sender_id`) AS `sent_message_count` 
		");
		$this->profileSql = new SqlLiteral("
			users.*, 
			((year(now()) - year(`users`.`birthdate`)) - (date_format(now(),'%m%d') < date_format(`users`.`birthdate`,'%m%d'))) AS `age`,
			COUNT(DISTINCT :posts.id) AS `post_count`, 
			COUNT(DISTINCT :comments.id) AS `comment_count`, 
			COUNT(DISTINCT :messages.id) AS `received_message_count`, 
			(SELECT COUNT(`messages`.`id`) FROM `messages` WHERE `users`.`id` = `messages`.`sender_id`) AS `sent_message_count` 
		");
	}

	/**
	 * Performs an authentication.
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		list($username, $password) = $credentials;

		// zjištění uživatele podle nicku nebo e-mailu
		$row = $this->database->table(self::TABLE_NAME) // v případě změny názvů sloupců použít ALIASY
				->where(self::COLUMN_EMAIL . ' = ? OR ' . self::COLUMN_NAME . ' = ?', $username, $username)->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('Nesprávné přihlašovací jméno.', self::IDENTITY_NOT_FOUND);

		} elseif (!Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('Nesprávné heslo.', self::INVALID_CREDENTIAL);

		} elseif (Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
			$row->update(array(
				self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
			));
		}
		
		// zkontroluje, zda je účet aktivován
		if (!$row[self::COLUMN_ACTIVATED]) {
			throw new InactiveAccountException($row[self::COLUMN_EMAIL]);
		}

		// vytvoří pole z údajů o uživateli
		$arr = $this->getById($row[self::COLUMN_ID])->toArray();

		return new Nette\Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
	}

	/**
	 * Provede změnu hesla přihlášeného uživatele.
	 * @param $id
	 * @param $oldPassword
	 * @param $password
	 * @throws Nette\Security\AuthenticationException
	 */
	public function changePassword($id, $oldPassword, $password)
	{
		$row = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID, $id)->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('Neplatné ID.', self::IDENTITY_NOT_FOUND);
		}
		elseif (!Passwords::verify($oldPassword, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('Nesprávné heslo.', self::INVALID_CREDENTIAL);
		}

		$row->update(array(
			self::COLUMN_PASSWORD_HASH => Passwords::hash($password),
		));
	}

	/**
	 * Provede změnu hesla nebo zrušení žádosti o změnu.
	 * @param $email
	 * @param $token
	 * @param string $newPassword nové heslo (zrušení žádosti, pokud je NULL)
	 * @throws InvalidTokenException
	 */
	public function recoverPassword($email, $token, $newPassword = NULL) {
		$update = array(
			self::COLUMN_PASSWORD_TOKEN_HASH => NULL,
			self::COLUMN_PASSWORD_TOKEN_HASH_VALIDITY => NULL,
		);

		if ($newPassword) {
			$update[self::COLUMN_PASSWORD_HASH] = Passwords::hash($newPassword);
		}

		$recovered = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)
			->where(self::COLUMN_PASSWORD_TOKEN_HASH, hash('sha512', $token))->update($update);

		if (!$recovered) {
			throw new InvalidTokenException;
		}
	}
	
	/**
	 * Ověří, zda se účet se zadanou e-mailovou adresou nachází v databázi.
	 * @param unknown $email e-mailová adresa
	 * @throws Nette\Security\AuthenticationException nenalezení adresy
	 */
	public function verifyEmail($email) {
		$row = $this->database->table(self::TABLE_NAME)
					->where(self::COLUMN_EMAIL, $email)->fetch();
		
		if (!$row) {
			throw new Nette\Security\AuthenticationException('Účet se zadanou e-mailovou adresou nebyl nalezen.', self::IDENTITY_NOT_FOUND);
		}
		
		if (!$row[self::COLUMN_ACTIVATED]) {
			throw new InactiveAccountException($row[self::COLUMN_EMAIL]);
		}
	}
	
	/**
	 * Aktivuje nově registrovaný účet.
	 * @param unknown $email e-mailová adresa účtu
	 * @param unknown $token aktivační kód z e-mailu
	 * @throws InvalidTokenException neplatný kód
	 */
	public function activate($email, $token) {
		
		// aktivuje účet, vymaže token a časové údaje o platnosti
		$activated = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)
						->where(self::COLUMN_PASSWORD_TOKEN_HASH, hash('sha512', $token))
						->where(self::COLUMN_PASSWORD_TOKEN_HASH_VALIDITY, new SqlLiteral('>= NOW()'))->update(array(
						self::COLUMN_ACTIVATED => TRUE,
						self::COLUMN_PASSWORD_TOKEN_HASH => NULL,
						self::COLUMN_PASSWORD_TOKEN_HASH_VALIDITY => NULL,
						self::COLUMN_TOKEN_RESEND_BLOCKED => NULL,
					));

		// pokud aktivace neproběhla (neplatné údaje)
		if (!$activated) {
			throw new InvalidTokenException;
		}
	}
	
	/**
	 * Vygeneruje token pro odkaz na aktivaci účtu nebo obnovu hesla a nastaví jeho platnost.
	 * @param unknown $email e-mailová adresa účtu
	 * @param string $newAccount TRUE, pokud byl účet právě vytvořen, jinak FALSE
	 * @throws ResendRejectedException odmítnutí požadavku
	 * @return string vygenerovaný token
	 */
	public function createToken($email, $newAccount = FALSE) {
		// zjištění, zda příslušný uživatel nemá blokované zaslání kódu
		$user = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)
		->where(new SqlLiteral(self::COLUMN_TOKEN_RESEND_BLOCKED . ' IS NULL OR ' 
				. self::COLUMN_TOKEN_RESEND_BLOCKED . ' < NOW()'))->getPrimary();
		
		// pokud není vráceno ID uživatele (zasílání kódu je blokováno)
		if (!$user) {
			throw new ResendRejectedException;
		}

		// vygenerování aktivačního kódu
		$token = hash('sha512',(uniqid(mt_rand(), TRUE)));
		
		// uložení hashe kódu a doby platnosti
		$array = array (
				self::COLUMN_PASSWORD_TOKEN_HASH => hash('sha512', $token),
				self::COLUMN_PASSWORD_TOKEN_HASH_VALIDITY => new SqlLiteral('NOW() + INTERVAL 1 DAY'),
		);
		
		// v případě požadavku na opětovné zaslání se uloží doba blokování dalšího požadavku
		if (!$newAccount) {
			$array[self::COLUMN_TOKEN_RESEND_BLOCKED] = new SqlLiteral('NOW() + INTERVAL 1 HOUR');
		}
		
		// aktualizace údajů v DB
		$this->database->table(self::TABLE_NAME)->where(self::COLUMN_EMAIL, $email)->update($array);
		
		return $token;
	}

	/**
	 * Odešle e-mail pro aktivaci účtu nebo obnovu hesla.
	 * @param unknown $email e-mailová adresa účtu
	 * @param unknown $url odkaz k provedení aktivace nebo obnovy hesla (obsahuje kontrolní kód)
	 * @param unknown $path cesta k HTML šabloně zprávy
	 */
	public function sendMail($email, $url, $path) {
		// data pro HTML šablonu e-mailu
		$params = array(
				'url' => $url,
		);
		$latte = new Engine;
		
		// vytvoření e-mailu
		$mail = new Message;
		$mail->setFrom('Kybersvět <info@kybersvet.cz>')
		->addTo($email)
		->setHtmlBody($latte->renderToString($path, $params));
		
		// odeslání e-mailu
		$mailer = new SendmailMailer;
		$mailer->send($mail);
	}

	/**
	 * Adds new user.
	 * @param  string
	 * @param  string
	 * @return void
	 */
	public function add($values)
	{
		try {
			// TODO zpřesnit výpočet věku
			$date = new DateTime((date('Y') - $values->age) . '-' . date('m') . '-' . date('d'));
			
			$email = $this->database->table(self::TABLE_NAME)->insert(array(
					self::COLUMN_EMAIL => $values->email,
					self::COLUMN_PASSWORD_HASH => Passwords::hash($values->password),
					self::COLUMN_NAME => $values->nickname,
					self::COLUMN_SEX => $values->sex,
					self::COLUMN_BIRTHDATE => $date,
			));
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
	}
	
	public function getById($id) {
		return $this->database->table(self::VIEW_USER_IDENTITIES)->where(self::COLUMN_ID, $id)->fetch();
	}

	public function getProfile($id) {
		return $this->database->table(self::TABLE_NAME)->select($this->profileSql)->where('users.id', $id)->fetch();
	}

	public function getPage($paginator, $page, $resultsPerPage) {
		$users = $this->database->table(self::TABLE_NAME)->select($this->userSql)->group('users.id');

		$paginator->setItemCount($users->count());
		$paginator->setItemsPerPage($resultsPerPage);
		$paginator->setPage($page ?: 1);

		return $users->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
	}

}

class DuplicateNameException extends \Exception
{}

class InvalidTokenException extends \Exception
{}

class ResendRejectedException extends \Exception
{}

class InactiveAccountException extends \Exception
{
	private $email;
	
	public function __construct($email) {
		$this->email = $email;
	}
	
	public function getAccountEmail() {
		return $this->email;
	}
}
