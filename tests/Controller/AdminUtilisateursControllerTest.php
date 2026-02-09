<?php

namespace App\Tests\Controller;

use App\Controller\AdminUtilisateursController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityRepository;

class AdminUtilisateursControllerTest extends TestCase {

    private AdminUtilisateursController $controller;

    protected function setUp(): void {
        $this->controller = new AdminUtilisateursController();
    }

    //Tests validation création utilisateur
    public function testValiderDonneesCreation(): void {
        $method = $this->getPrivateMethod('validerDonneesCreation');

        // Données valides
        $donneesValid = [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'plainPassword' => 'password123',
            'passwordConfirm' => 'password123',
            'role' => 'ROLE_USER'
        ];
        $this->assertEquals([], $method->invoke($this->controller, $donneesValid));

        // Rôle invalide
        $donneesInvalidRole = $donneesValid;
        $donneesInvalidRole['role'] = 'ROLE_INVALID';
        $this->assertEquals(['Rôle invalide.'], $method->invoke($this->controller, $donneesInvalidRole));

        // Champs manquants
        $donneesMissing = $donneesValid;
        $donneesMissing['nom'] = '';
        $this->assertEquals(['Nom, prénom et mot de passe sont requis.'],
                $method->invoke($this->controller, $donneesMissing));

        // Mots de passe différents
        $donneesBadPassword = $donneesValid;
        $donneesBadPassword['passwordConfirm'] = 'badpass';
        $this->assertEquals(['Les mots de passe ne correspondent pas.'],
                $method->invoke($this->controller, $donneesBadPassword));
    }

    public function testExtraireDonneesFormulaire(): void {
        $method = $this->getPrivateMethod('extraireDonneesFormulaire');
        $request = new Request([], [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'password' => 'pass123',
            'password_confirm' => 'pass123',
            'role' => 'ROLE_ADMIN'
        ]);

        $donnees = $method->invoke($this->controller, $request);

        $this->assertEquals('dupont jean', $donnees['username']);
        $this->assertEquals('Dupont', $donnees['nom']);
        $this->assertEquals('Jean', $donnees['prenom']);
        $this->assertEquals('ROLE_ADMIN', $donnees['role']);
    }

    //Tests génération username automatique
    public function testUsernameGeneration(): void {
        $method = $this->getPrivateMethod('extraireDonneesFormulaire');
        $request = new Request([], [
            'nom' => '  Martin-L e  ',
            'prenom' => '  Pierre   ',
            'password' => 'pass'
        ]);

        $donnees = $method->invoke($this->controller, $request);
        $this->assertEquals('martin-l e pierre', $donnees['username']);
    }

    //Tests logging création
    public function testLoggerCreation(): void {
        $emMock = $this->createMock(EntityManagerInterface::class);
        $userMock = $this->createMock(User::class);
        // Mock les getters nécessaires
        $userMock->method('getId')->willReturn(1);
        $userMock->method('getUsername')->willReturn('testuser');
        $userMock->method('getNom')->willReturn('Test');
        $userMock->method('getPrenom')->willReturn('User');
        $userMock->method('getRoles')->willReturn(['ROLE_USER']);
        $userMock->method('isActive')->willReturn(true);

        $emMock->expects($this->once())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $method = $this->getPrivateMethod('loggerCreation');
        $method->invoke($this->controller, $emMock, $userMock);
    }

    //Tests JSON error helper
    public function testJsonError(): void {
        $reflection = new \ReflectionClass($this->controller);
        $containerProp = $reflection->getProperty('container');
        $containerProp->setAccessible(true);
        $containerMock = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $containerProp->setValue($this->controller, $containerMock);

        $method = $this->getPrivateMethod('jsonError');
        $response = $method->invoke($this->controller, 'Erreur test');

        $this->assertJsonStringEqualsJsonString(
                json_encode(['success' => false, 'message' => 'Erreur test']),
                $response->getContent()
        );
    }

    private function getPrivateMethod(string $name): \ReflectionMethod {
        $method = new \ReflectionMethod(AdminUtilisateursController::class, $name);
        $method->setAccessible(true);
        return $method;
    }

    public function testUtilisateurExistant(): void {
        $method = $this->getPrivateMethod('utilisateurExistant');
        $emMock = $this->createMock(EntityManagerInterface::class);
        $repoMock = $this->createMock(EntityRepository::class);
        // User existe
        $repoMock->method('findOneBy')->willReturn(new User());
        $emMock->method('getRepository')->willReturn($repoMock);
        $this->assertFalse($method->invoke($this->controller, $emMock, 'existing'));
    }

    public function testCreationUtilisateurComplete(): void {
        $method = $this->getPrivateMethod('creerUtilisateur');
        $passwordHasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $donnees = [
            'username' => 'test.user',
            'nom' => 'Test',
            'prenom' => 'User',
            'plainPassword' => 'pass123',
            'role' => 'ROLE_USER'
        ];

        $userMock = $this->createMock(User::class);
        $passwordHasherMock->method('hashPassword')->willReturn('hashedpass');

        $user = $method->invoke($this->controller, $donnees, $passwordHasherMock);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test.user', $user->getUsername());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }
}
