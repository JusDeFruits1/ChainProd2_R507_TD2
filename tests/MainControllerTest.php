<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use App\Entity\Contact;

class MainControllerTest extends WebTestCase
{
	public function testSubmitContactPersists()
	{
		$projectDir = dirname(__DIR__);
		$dbFile = $projectDir . '/var/test_sqlite.db';
		if (file_exists($dbFile)) {
			@unlink($dbFile);
		}
		$dbPath = str_replace('\\', '/', $dbFile);
		$dbUrl = 'sqlite:///' . $dbPath;
		putenv('DATABASE_URL=' . $dbUrl);
		$_ENV['DATABASE_URL'] = $dbUrl;
		$_SERVER['DATABASE_URL'] = $dbUrl;
		if (false === getenv('APP_BASE_DOMAIN')) {
			putenv('APP_BASE_DOMAIN=localhost');
			$_ENV['APP_BASE_DOMAIN'] = 'localhost';
			$_SERVER['APP_BASE_DOMAIN'] = 'localhost';
		}

		$client = static::createClient();
		$container = static::getContainer();
		$doctrine = $container->get('doctrine');
		$em = $doctrine->getManager();


		$classes = [$em->getClassMetadata(Contact::class)];
		$schemaTool = new SchemaTool($em);
		$schemaTool->dropSchema($classes);
		$schemaTool->createSchema($classes);

		$client->request('GET', '/');
		$client->submitForm('Envoyer', [
			'form[firstName]' => 'Jane',
			'form[name]' => 'Tester',
			'form[message]' => 'Message de test',
		]);

		$em->flush();
		$em->clear();

		$repo = $doctrine->getRepository(Contact::class);
		$results = $repo->findBy(['firstName' => 'Jane', 'name' => 'Tester']);

		$this->assertCount(1, $results, 'Un contact doit avoir été persisté en base.');
		$contact = $results[0];
		$this->assertSame('Message de test', $contact->getMessage());
		$this->assertSame('New', $contact->getStatus());
		$this->assertNotNull($contact->getCreatedAt());
	}

	public function testIndexPageLoads()
	{
		if (false === getenv('DATABASE_URL')) {
			putenv('DATABASE_URL=sqlite:///:memory:');
			$_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
			$_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
		}
		if (false === getenv('APP_BASE_DOMAIN')) {
			putenv('APP_BASE_DOMAIN=localhost');
			$_ENV['APP_BASE_DOMAIN'] = 'localhost';
			$_SERVER['APP_BASE_DOMAIN'] = 'localhost';
		}
		$client = static::createClient();
		$client->request('GET', '/');

		$response = $client->getResponse();

		$status = $response->getStatusCode();
		if ($status !== 200) {
			fwrite(STDERR, "\n--- Status : (status $status) ---\n");
			fwrite(STDERR, $response->getContent() . "\n--- ---\n");
		}
		$this->assertSame(200, $status, 'La page d\'accueil doit répondre avec 200.');

		$content = $response->getContent();

		$this->assertStringContainsString('Formulaire de contact', $content, 'Le titre du formulaire doit apparaître.');
		$this->assertStringContainsString('<form', $content, 'La page doit contenir un formulaire HTML.');
		$this->assertStringContainsString('Envoyer', $content, 'Le bouton d\'envoi doit être présent.');
	}

	public function testListPaginationNavigation()
	{

		$projectDir = dirname(__DIR__);
		$dbFile = $projectDir . '/var/test_sqlite.db';
		if (file_exists($dbFile)) {
			@unlink($dbFile);
		}
		$dbPath = str_replace('\\', '/', $dbFile);
		$dbUrl = 'sqlite:///' . $dbPath;
		putenv('DATABASE_URL=' . $dbUrl);
		$_ENV['DATABASE_URL'] = $dbUrl;
		$_SERVER['DATABASE_URL'] = $dbUrl;
		if (false === getenv('APP_BASE_DOMAIN')) {
			putenv('APP_BASE_DOMAIN=localhost');
			$_ENV['APP_BASE_DOMAIN'] = 'localhost';
			$_SERVER['APP_BASE_DOMAIN'] = 'localhost';
		}

		$client = static::createClient();
		$container = static::getContainer();
		$doctrine = $container->get('doctrine');
		$em = $doctrine->getManager();

		$classes = [$em->getClassMetadata(Contact::class)];
		$tool = new SchemaTool($em);
		$tool->dropSchema($classes);
		$tool->createSchema($classes);


		$now = new \DateTimeImmutable('now');
		for ($i = 1; $i <= 5; $i++) {
			$c = new Contact();
			$c->setFirstName('FN' . $i);
			$c->setName('N' . $i);
			$c->setMessage('M' . $i);
			$c->setStatus([1 => 'treated', 2 => 'archived', 3 => 'new', 4 => 'treated', 5 => 'new'][$i]);
			$c->setCreatedAt($now->modify('+' . $i . ' minutes'));
			$em->persist($c);
		}
		$em->flush();
		$em->clear();

		$client->request('GET', '/liste/1');
		$body = $client->getResponse()->getContent();
		$this->assertStringContainsString('Page 1 sur 3', $body);
		$this->assertStringContainsString('FN5', $body);
		$this->assertStringContainsString('FN4', $body);
		$this->assertStringNotContainsString('FN3', $body);
		$this->assertStringContainsString('Suivant', $body);
		$this->assertStringNotContainsString('Précédent', $body);


		$client->request('GET', '/liste/2');
		$body = $client->getResponse()->getContent();
		$this->assertStringContainsString('Page 2 sur 3', $body);
		$this->assertStringContainsString('FN3', $body);
		$this->assertStringContainsString('FN2', $body);
		$this->assertStringNotContainsString('FN5', $body);
		$this->assertStringContainsString('Suivant', $body);
		$this->assertStringContainsString('Précédent', $body);


		$client->request('GET', '/liste/3');
		$body = $client->getResponse()->getContent();
		$this->assertStringContainsString('Page 3 sur 3', $body);
		$this->assertStringContainsString('FN1', $body);
		$this->assertStringNotContainsString('FN2', $body);
		$this->assertStringNotContainsString('Suivant', $body);
		$this->assertStringContainsString('Précédent', $body);
	}

	public function testListFilterByStatus()
	{
		$projectDir = dirname(__DIR__);
		$dbFile = $projectDir . '/var/test_sqlite.db';
		if (file_exists($dbFile)) {
			@unlink($dbFile);
		}
		$dbPath = str_replace('\\', '/', $dbFile);
		$dbUrl = 'sqlite:///' . $dbPath;
		putenv('DATABASE_URL=' . $dbUrl);
		$_ENV['DATABASE_URL'] = $dbUrl;
		$_SERVER['DATABASE_URL'] = $dbUrl;
		if (false === getenv('APP_BASE_DOMAIN')) {
			putenv('APP_BASE_DOMAIN=localhost');
			$_ENV['APP_BASE_DOMAIN'] = 'localhost';
			$_SERVER['APP_BASE_DOMAIN'] = 'localhost';
		}

		$client = static::createClient();
		$container = static::getContainer();
		$doctrine = $container->get('doctrine');
		$em = $doctrine->getManager();

		$classes = [$em->getClassMetadata(Contact::class)];
		$tool = new SchemaTool($em);
		$tool->dropSchema($classes);
		$tool->createSchema($classes);

		$now = new \DateTimeImmutable('now');

		$fixtures = [
			['FN1', 'treated', '+1 minutes'],
			['FN2', 'new', '+2 minutes'],
			['FN3', 'archived', '+3 minutes'],
			['FN4', 'new', '+4 minutes'],
		];
		foreach ($fixtures as [$fn, $status, $delta]) {
			$c = new Contact();
			$c->setFirstName($fn);
			$c->setName('X');
			$c->setMessage('Y');
			$c->setStatus($status);
			$c->setCreatedAt($now->modify($delta));
			$em->persist($c);
		}
		$em->flush();
		$em->clear();

		$client->request('GET', '/liste/1?status=new');
		$body = $client->getResponse()->getContent();
		$this->assertStringContainsString('Page 1 sur 1', $body);
		$this->assertStringContainsString('FN4', $body);
		$this->assertStringContainsString('FN2', $body);
		$this->assertStringNotContainsString('FN3', $body);
		$this->assertStringNotContainsString('FN1', $body);
		$this->assertStringNotContainsString('Suivant', $body);
		$this->assertStringNotContainsString('Précédent', $body);
	}
}
