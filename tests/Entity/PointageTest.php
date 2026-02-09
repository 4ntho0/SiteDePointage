<?php

namespace App\Tests\Entity;

use App\Entity\Pointage;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PointageTest extends TestCase
{
    public function testTotalTravailSecondsSansHeures(): void
    {
        $pointage = new Pointage();
        $this->assertNull($pointage->getTotalTravailSeconds());
    }

    public function testTotalTravailSecondsEntreeSeulement(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $this->assertNull($pointage->getTotalTravailSeconds());
    }

    /**
     * Journée complète SANS pause : 08:00 → 17:00 = 9h = 32400s
     */
    public function testTotalTravailSecondsSansPause(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $pointage->setHeureSortie(new \DateTime('17:00:00'));

        $this->assertEquals(32400, $pointage->getTotalTravailSeconds());
    }

    /**
     * Journée AVEC pause : 08:00 → 17:00 - 12:00→13:00 = 8h = 28800s
     */
    public function testTotalTravailSecondsAvecPause(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $pointage->setHeureSortie(new \DateTime('17:00:00'));
        $pointage->setHeureDebutPause(new \DateTime('12:00:00'));
        $pointage->setHeureFinPause(new \DateTime('13:00:00'));

        $this->assertEquals(28800, $pointage->getTotalTravailSeconds());
    }

    /**
     * Pause non terminée → ne pas soustraire
     */
    public function testTotalTravailSecondsPauseNonTerminee(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $pointage->setHeureSortie(new \DateTime('17:00:00'));
        $pointage->setHeureDebutPause(new \DateTime('12:00:00'));
        // Pas de heureFinPause

        $this->assertEquals(32400, $pointage->getTotalTravailSeconds());
    }

    /**
     * Sortie avant entrée → 0s
     */
    public function testTotalTravailSecondsSortieAvantEntree(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('09:00:00'));
        $pointage->setHeureSortie(new \DateTime('08:00:00'));

        $this->assertEquals(0, $pointage->getTotalTravailSeconds());
    }

    public function testTotalTravailFormatted(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $pointage->setHeureSortie(new \DateTime('12:00:00'));

        $this->assertEquals('04:00:00', $pointage->getTotalTravailFormatted());
    }

    public function testTotalTravailFormattedAvecPause(): void
    {
        $pointage = new Pointage();
        $pointage->setHeureEntree(new \DateTime('08:00:00'));
        $pointage->setHeureSortie(new \DateTime('17:00:00'));
        $pointage->setHeureDebutPause(new \DateTime('12:00:00'));
        $pointage->setHeureFinPause(new \DateTime('13:00:00'));

        $this->assertEquals('08:00:00', $pointage->getTotalTravailFormatted());
    }

    public function testTotalTravailFormattedNull(): void
    {
        $pointage = new Pointage();
        $this->assertNull($pointage->getTotalTravailFormatted());
    }
}
