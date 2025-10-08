<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use App\Entity\Contact;

class MainControllerTest extends WebTestCase
{
	protected static function getKernelClass(): string
	{
		return \App\Kernel::class;
	}

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

		
		$classes = [ $em->getClassMetadata(Contact::class) ];
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
		$this->assertSame('pending', $contact->getStatus());
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
}

