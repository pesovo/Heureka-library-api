<?php
/**
 * Created by PhpStorm.
 * User: tomas
 * Date: 17.5.16
 * Time: 21:05
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Book;
use AppBundle\Entity\BookHolder;
use AppBundle\Entity\BookUniq;
use AppBundle\Entity\Form;
use AppBundle\Entity\InternalBook;
use AppBundle\Entity\Lang;
use AppBundle\Entity\Locality;
use AppBundle\Entity\Tag;
use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * API controller.
 *
 * @Route("/api/v1")
 */
class ApiController extends Controller
{

	/**
	 * get languages
	 *
	 * @Route("/filters/lang", name="api_lang")
	 * @Method("GET")
	 */
	public function langAction()
	{
		$em = $this->getDoctrine()->getManager();
		$languages = $em->getRepository(Lang::class)->findAll();
		return new JsonResponse($languages);
	}

	/**
	 * get forms
	 *
	 * @Route("/filters/form", name="api_form")
	 * @Method("GET")
	 */
	public function formAction()
	{
		$em = $this->getDoctrine()->getManager();
		$forms = $em->getRepository(Form::class)->findAll();
		return new JsonResponse($forms);
	}

	/**
	 * get localities
	 *
	 * @Route("/filters/locality", name="api_locality")
	 * @Method("GET")
	 */
	public function localityAction()
	{
		$em = $this->getDoctrine()->getManager();
		$localities = $em->getRepository(Locality::class)->findAll();
		return new JsonResponse($localities);
	}

	/**
	 * get tags
	 *
	 * @Route("/filters/tag", name="api_tag")
	 * @Method("GET")
	 */
	public function tagAction()
	{
		$em = $this->getDoctrine()->getManager();
		$tags = $em->getRepository(Tag::class)->findAll();
		return new JsonResponse($tags);
	}

	/**
	 * get books
	 *
	 * @Route("/books", name="api_books")
	 * @Method("GET")
	 */
	public function bookAction()
	{
		$em = $this->getDoctrine()->getManager();

		$workingBooks = self::getBookDql($em)
			->getQuery()
			->getResult();

		return new JsonResponse(self::prepareBook($em, $workingBooks));
	}

	/**
	 * get books
	 *
	 * @Route("/books-rate", name="api_books_rate")
	 * @Method("GET")
	 */
	public function bookRateAction()
	{
		$em = $this->getDoctrine()->getManager();

		$workingBooks = self::getBookDqlWithRate($em)
			->getQuery()
			->getResult();

		return new JsonResponse(self::prepareBook($em, $workingBooks));
	}

	/**
	 * get internal books
	 *
	 * @Route("/internal-books", name="api_internalbook")
	 * @Method("GET")
	 */
	public function internalBookAction()
	{
		$em = $this->getDoctrine()->getManager();

		$qb = $em->createQueryBuilder();
		$internalBooks = $qb->select(['b.date', 'b.id'])
			->from(InternalBook::class, 'b')
			->orderBy('b.date', 'DESC')
			->getQuery()
			->getResult();

		$books = [];
		foreach ($internalBooks as $b) {
			$books[] = [
				'id' => $b['id'],
				'date' => $b['date']->format('Y-m-d'),
			];
		}

		return new JsonResponse($books);
	}

	/**
	 * get my books
	 *
	 * @Route("/books/my/{user}", name="api_mybook")
	 * @Method("GET")
	 */
	public function myBookAction($user)
	{
		$em = $this->getDoctrine()->getManager();
		$workingBooks = self::getBookDqlWithRate($em)
			->innerJoin('bh.user', 'u')
			->where('bh.to IS NULL AND u.email = :user')
			->setParameter('user', $user)
			->getQuery()
			->getResult();

		return new JsonResponse(self::prepareBook($em, $workingBooks));
	}

	/**
	 * get book detail
	 *
	 * @Route("/book/{code}", name="api_book")
	 * @Method("GET")
	 */
	public function bookDetailAction($code)
	{
		$em = $this->getDoctrine()->getManager();
		$workingBooks = self::getBookDql($em)
			->where('bu.code = :code')
			->setParameter('code', $code)
			->getQuery()
			->getResult();

		return new JsonResponse(empty(self::prepareBook($em, $workingBooks)) ? [] : self::prepareBook($em, $workingBooks)[0]);
	}

	/**
	 * get book history
	 *
	 * @Route("/book/history/{id}", name="api_book_history")
	 * @Method("GET")
	 */
	public function bookHistoryAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$qb = $em->createQueryBuilder();
		$bookHisotry = $qb->select(['u.name AS user_name', 'bh.type AS type', 'bh.from AS date', 'bh.to AS return', 'bh.rate AS rate', 'bh.rateText AS text_rate'])
			->from(BookHolder::class, 'bh')
			->innerJoin('bh.bookUniq', 'bu')
			->innerJoin('bu.book', 'b')
			->innerJoin('bh.user', 'u')
			->where('b.bookId = :id')
			->setParameter('id', $id)
			->orderBy('bh.from', 'DESC')
			->getQuery()
			->getResult();

		$bHistory = [];
		foreach ($bookHisotry as $history) {
			$bHistory[] = [
				'user_name' => $history['user_name'],
				'type' => $history['type'],
				'date' => $history['date']->format("Y-m-d"),
				'return' => $history['return'] != null ? $history['return']->format("Y-m-d") : '',
				'rate' => $history['rate'],
				'text_rate' => $history['text_rate'],
			];
		}

		return new JsonResponse($bHistory);
	}

	/**
	 * get my books history
	 *
	 * @Route("/book/user-history/{user}", name="api_my_book_history")
	 * @Method("GET")
	 */
	public function myBookHistoryAction($user)
	{
		$em = $this->getDoctrine()->getManager();
		$workingBooks = self::getBookDqlWithRate($em)
			->innerJoin('bh.user', 'u')
			->where('u.email = :email')
			->setParameter('email', $user)
			->getQuery()
			->getResult();

		return new JsonResponse(self::prepareBook($em, $workingBooks));
	}

	/**
	 * reserve book
	 *
	 * @Route("/book/reservation/{id}/user/{user}", name="api_book_reservation")
	 * @Method("POST")
	 */
	public function bookReservationAction($id, $user)
	{
		$em = $this->getDoctrine()->getManager();

		$bookHolder = new BookHolder();
		$bookHolder->setBook($em->find(Book::class, $id));
		$bookHolder->setUser($em->find(User::class, $user));
		$bookHolder->setType(BookHolder::TYPE_RESERVATION);
		$bookHolder->setFrom(new \DateTime());
		$em->persist($bookHolder);
		$em->flush();

		return new JsonResponse(['info' => 'Rezervace proběhla v pořádku']);
	}

	/**
	 * borrow book
	 *
	 * @Route("/book/borrow/{code}/user/{user}", name="api_book_borrow")
	 * @Method("POST")
	 */
	public function bookBorrowAction($code, $user)
	{
		$em = $this->getDoctrine()->getManager();

		$bookUniq = $em->find(BookUniq::class, $code);
		if ($bookUniq == null) {
			return new JsonResponse(['info' => 'Načtená kniha neexistuje!']);
		}

		$bookHolder = new BookHolder();
		$bookHolder->setBook($em->find(Book::class, $bookUniq->getBook()->getBookId()));
		$bookHolder->setBookUniq($em->find(BookUniq::class, $code));
		$bookHolder->setUser($em->find(User::class, $user));
		$bookHolder->setType(BookHolder::TYPE_BORROW);
		$bookHolder->setFrom(new \DateTime());
		$em->persist($bookHolder);
		$em->flush();

		return new JsonResponse(['info' => 'Vypůjčení proběhla v pořádku']);
	}

	/**
	 * borrow book check
	 *
	 * @Route("/book/borrow/{code}/user/{user}", name="api_book_borrow_check")
	 * @Method("GET")
	 */
	public function bookCheckBeforeBorrowAction($code, $user)
	{
		$em = $this->getDoctrine()->getManager();
		$qb = $em->createQueryBuilder();

		$bookUniq = $em->find(BookUniq::class, $code);
		if ($bookUniq == null) {
			return new JsonResponse(['info' => 'Načtená kniha neexistuje!']);
		}

		$bookReservation = $qb->select(['u.name AS user', 'bh.from'])
			->from(BookHolder::class, 'bh')
			->innerJoin('bh.book', 'b')
			->innerJoin('bh.user', 'u')
			->where('b.bookId = :id AND bh.type = :type')
			->setParameter('id', $bookUniq->getBook()->getBookId())
			->setParameter('type', BookHolder::TYPE_RESERVATION)
			->orderBy('bh.from', 'DESC')
			->getQuery()
			->getResult();

		$reservations = [];
		foreach ($bookReservation as $r)
		{
			$reservations[] = [
				'user' => $r['user'],
				'from' => $r['from']->format('Y-m-d'),
			];
		}

		return new JsonResponse($reservations);
	}

	/**
	 * return book
	 *
	 * @Route("/book/return/id/{id}/user/{user}/place/{place}/rate/{rate}/rate_text/{ratetext}", name="api_book_return")
	 * @Method("POST")
	 */
	public function bookReturnAction($id, $user, $place, $rate, $ratetext)
	{
		$em = $this->getDoctrine()->getManager();

		if($place == 0) {
			$holder = new BookHolder();
			$holder->setBook($em->find(Book::class, $id));
			$holder->setUser($em->find(User::class, $user));
			$holder->setType(BookHolder::TYPE_BORROW);
			$holder->setFrom(new \DateTime());
		} else {
			// update holder
			$holderRepository = $em->getRepository(BookHolder::class);
			$holder = $holderRepository->findOneBy([
				'book' => $em->find(Book::class, $id),
				'user' => $em->find(User::class, $user),
			]);

			// update bookUniq place
			$bookUniq = $holder->getBookUniq();
			$bookUniq->setLocality($em->find(Locality::class, $place));
			$em->persist($bookUniq);
		}

		$holder->setRateText($ratetext);
		$holder->setRate($rate);
		$holder->setTo(new \DateTime());
		$em->persist($holder);

		$em->flush();

		return new JsonResponse(['info' => 'Vrácení proběhla v pořádku']);
	}

	/**
	 * return book image
	 *
	 * @Route("/book/image/{id}", name="api_book_image")
	 * @Method("GET")
	 */
	public function bookImageAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$book = $em->getRepository(Book::class)->find($id);
		$response = new Response(stream_get_contents($book->getImage()), 200, [
			'Content-Type' => 'image/jpeg',
		]);

		return $response;
	}

	/**
	 * return book
	 *
	 * @Route("/book/book/{id}", name="api_book_book")
	 * @Method("GET")
	 */
	public function bookBookAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$book = $em->getRepository(Book::class)->find($id);
		$response = new Response(stream_get_contents($book->getBook()), 200, [
			'Content-Type' => $book->getMime(),
		]);
		return $response;
	}

	/**
	 * return internal book
	 *
	 * @Route("/book/internal-book/{id}", name="api_book_internal_book")
	 * @Method("GET")
	 */
	public function bookInternalBookAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$internalBook = $em->getRepository(InternalBook::class)->find($id);
		$response = new Response(stream_get_contents($internalBook->getBook()), 200, [
			'Content-Type' => 'application/pdf',
		]);
		return $response;
	}

	/**
	 * add suer
	 *
	 * @Route("/add-user/{name}/email/{email}", name="api_add-user")
	 * @Method("POST")
	 */
	public function addUserAction($name, $email)
	{
		$em = $this->getDoctrine()->getManager();

		if($em->find(User::class, $email) != null) {
			return new JsonResponse(['info' => 'Váš požadavek byl přijat. Účet již existuje.']);
		}

		$user = new User();
		$user->setName($name);
		$user->setEmail($email);
		$user->setAuth(false);

		$em->persist($user);
		$em->flush();

		return new JsonResponse(['info' => 'Váš požadavek byl přijat']);
	}

	/**
	 * add user token
	 *
	 * @Route("/user-token/{email}/{token}", name="api_add-user-token")
	 * @Method("POST")
	 */
	public function addUserTokenAction($email, $token)
	{
		$em = $this->getDoctrine()->getManager();

		if($em->find(User::class, $email) == null) {
			return new JsonResponse([]);
		}

		$user = $em->find(User::class, $email);
		$user->setGoogleToken($token);

		$em->persist($user);
		$em->flush();

		return new JsonResponse(['info' => 'Token byl uložen']);
	}

	/**
	 * return apis
	 *
	 * @Route("/list", name="api_list")
	 * @Method("GET")
	 */
	public function apiListAction()
	{
		return new JsonResponse([
			[
				'name' => 'Apiary',
				'address' => 'http://private-e52603-heurekalibrary.apiary-mock.com/v1/',
			],
			[
				'name' => 'Fiction-group.eu',
				'address' => 'https://library.fiction-group.eu/api/v1/',
			]
		]);
	}

	private function getBookDql($em)
	{
		$qb = $em->createQueryBuilder();
		return $qb->select(['b.bookId AS book_id', 'b.name', 'b.detailLink AS detail_link', 'l.lang AS lang', 'f.form AS form', 'COUNT(bu.code) AS total', 'b.mime'])
			->from(Book::class, 'b')
			->innerJoin('b.lang', 'l')
			->innerJoin('b.form', 'f')
			->leftJoin(BookUniq::class, 'bu', 'WITH', 'b.bookId = bu.book')
			->groupBy('b.bookId, b.name, b.detailLink, l.lang, f.form')
			->orderBy('b.name', 'ASC')
			;
	}

	private function getBookDqlWithRate($em)
	{
		$qb = $em->createQueryBuilder();
		return $qb->select([
			'b.bookId AS book_id',
			'b.name',
			'b.detailLink AS detail_link',
			'l.lang AS lang',
			'f.form AS form',
			'COUNT(bu.code) AS total',
			'AVG(bh.rate) AS avg_rate',
			'COUNT(bh.rate) AS cnt_rate',
			'b.mime'
		])
			->from(Book::class, 'b')
			->innerJoin('b.lang', 'l')
			->innerJoin('b.form', 'f')
			->leftJoin(BookUniq::class, 'bu', 'WITH', 'b.bookId = bu.book')
			->leftJoin(BookHolder::class, 'bh', 'WITH', 'bu.code = bh.bookUniq')
			->groupBy('b.bookId, b.name, b.detailLink, l.lang, f.form')
			->orderBy('b.name', 'ASC')
			;
	}

	private function prepareBook($em, $workingBooks)
	{
		$books = [];
		foreach ($workingBooks as $book) {

			$book['tags'] = self::getTags($em, $book['book_id']);
			$book['available'] = self::getAvailables($em, $book['book_id']);
			$book['holders'] = self::getHolders($em, $book['book_id']);
			$books[] = $book;
		}
		return $books;
	}

	private function getHolders($em, $bookId)
	{
		$availableQb = $em->createQueryBuilder();
		$holders = $availableQb->select(['u.name AS user', 'bh.from'])
			->from(Book::class, 'b')
			->innerJoin(BookUniq::class, 'bu', 'WITH', 'b.bookId = bu.book')
			->innerJoin(BookHolder::class, 'bh', 'WITH', 'bh.bookUniq = bu.code')
			->innerJoin('bh.user', 'u')
			->where('bh.to IS NULL')
			->andWhere('b.bookId = :bookId')
			->orderBy('bh.from', 'ASC')
			->setParameter('bookId', $bookId)
			->getQuery()
			->getResult();

		$clearHolders = [];
		foreach ($holders as $holder) {
			$clearHolders[] = [
				'user' => $holder['user'],
				'from' => $holder['from']->format('Y-m-d'),
			];
		}

		return $clearHolders;
	}

	private function getAvailables($em, $bookId)
	{

		$locality = $em->getRepository(Locality::class);
		$data = [];
		foreach($locality->findAll() as $locality) {

			$availableQb = $em->createQueryBuilder();
			$available = $availableQb->select(['(COUNT(bu.code) - COUNT(bh.id)) AS available'])
				->from(Book::class, 'b')
				->leftJoin(BookUniq::class, 'bu', 'WITH', 'b.bookId = bu.book')
				->leftJoin(BookHolder::class, 'bh', 'WITH', 'bh.bookUniq = bu.code')
				->where('bh.to IS NULL')
				->andWhere('b.bookId = :bookId')
				->andWhere('bu.locality = :locality')
				->setParameter('bookId', $bookId)
				->setParameter('locality', $locality->getId())
				->getQuery();

			$data[] = [
				'place' => $locality->getName(),
				'available' => $available->getSingleResult()['available'],
			];
		}
		return $data;
	}

	private function getTags($em, $bookId)
	{
		$tagQb = $em->createQueryBuilder();
		return $tagQb->select(['t.tag'])
			->from(Book::class, 'b')
			->innerJoin('b.tags', 't')
			->where('b.bookId = :bookId')
			->orderBy('t.tag', 'ASC')
			->setParameter('bookId', $bookId)
			->getQuery()
			->getResult();
	}

}
