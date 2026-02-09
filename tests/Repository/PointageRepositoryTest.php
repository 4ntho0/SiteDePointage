<?php


namespace App\Tests\Repository;

use App\Entity\Pointage;
use App\Entity\User;
use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PointageRepositoryTest extends KernelTestCase {

    private $em;
    private $repo;

    protected function setUp(): void {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Pointage::class);

        $this->viderTablesTest();
    }

    private function viderTablesTest(): void {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM pointage');
        $conn->executeStatement('DELETE FROM user');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $conn->executeStatement('ALTER TABLE user AUTO_INCREMENT = 1');
        $conn->executeStatement('ALTER TABLE pointage AUTO_INCREMENT = 1');
    }

    private function createUser(string $username): User {
        // Vérifier si existe déjà (évite duplicata)
        $existing = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing) {
            return $existing;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setNom('Test Nom');
        $user->setPrenom('Test Prenom');
        $user->setPassword('dummy');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createPointage(User $user, string $date, string $heureEntree = '08:00:00'): Pointage {
        $p = new Pointage();
        $p->setUtilisateur($user);
        $p->setDatePointage(new \DateTime($date));
        $p->setHeureEntree(new \DateTime($date . ' ' . $heureEntree));

        $p->setLatitudeEntree('46.8210677');
        $p->setLongitudeEntree('1.674782');

        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    public function testFindAllOrderByFieldParDate(): void {
        $user = $this->createUser('alice');
        $this->createPointage($user, '2025-01-02', '09:00:00');
        $this->createPointage($user, '2025-01-01', '08:00:00');

        $result = $this->repo->findAllOrderByField('datePointage', 'ASC', 'alice');

        $this->assertCount(2, $result);
        $this->assertEquals('2025-01-01', $result[0]->getDatePointage()->format('Y-m-d'));
    }

    public function testGetAllUsernames(): void {
        $u1 = $this->createUser('charles');
        $u2 = $this->createUser('bob');
        $this->createPointage($u1, '2025-01-01');
        $this->createPointage($u2, '2025-01-02');

        $rows = $this->repo->getAllUsernames();
        $usernames = array_column($rows, 'username');

        $this->assertContains('bob', $usernames);
        $this->assertContains('charles', $usernames);
    }

    public function testFindAllOrderByFieldParUtilisateur(): void {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->createPointage($alice, '2025-01-01');
        $this->createPointage($bob, '2025-01-02');

        $result = $this->repo->findAllOrderByField('utilisateur', 'ASC');

        $this->assertCount(2, $result);
        $this->assertEquals('alice', $result[0]->getUtilisateur()->getUsername());
    }

    public function testCountFilteredAvecFiltres(): void {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->createPointage($alice, '2025-01-01');
        $this->createPointage($bob, '2025-01-02');

        $start = new \DateTime('2025-01-01');
        $end = new \DateTime('2025-01-01');

        $count = $this->repo->countFiltered('alice', $start, $end);
        $this->assertEquals(1, $count);
    }

    public function testFindAllOrderByFieldWithLimit(): void {
        $user = $this->createUser('alice');
        $this->createPointage($user, '2025-01-03');
        $this->createPointage($user, '2025-01-02');
        $this->createPointage($user, '2025-01-01');

        $result = $this->repo->findAllOrderByField('datePointage', 'DESC', 'alice', 2);

        $this->assertCount(2, $result);
        $this->assertEquals('2025-01-03', $result[0]->getDatePointage()->format('Y-m-d'));
    }
}
