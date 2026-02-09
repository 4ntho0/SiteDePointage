<?php


namespace App\Tests\Validations;

use App\Entity\Pointage;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PointageValidationTest extends KernelTestCase {

    private $validator;

    protected function setUp(): void {
        self::bootKernel();
        $container = static::getContainer();
        $this->validator = $container->get('validator');
    }

    public function testValidationChampsObligatoires(): void {
        $pointage = new Pointage();

        $violations = $this->validator->validate($pointage);

        $this->assertGreaterThanOrEqual(3, count($violations));

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        $this->assertContains('La date de pointage est obligatoire', $messages);
        $this->assertContains('L\'heure d\'entrée est obligatoire pour un pointage valide', $messages);
        $this->assertContains('L\'utilisateur est obligatoire', $messages);
    }

    public function testValidationPointageComplet(): void {
        $user = new User();
        $user->setUsername('test');
        $user->setNom('Test');
        $user->setPrenom('Test');
        $user->setPassword('dummy');
        $user->setIsActive(true);

        $pointage = new Pointage();
        $pointage->setUtilisateur($user);
        $pointage->setDatePointage(new \DateTime());
        $pointage->setHeureEntree(new \DateTime('08:00:00'));

        $violations = $this->validator->validate($pointage);
        $this->assertCount(0, $violations);
    }
}
