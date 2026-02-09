<?php

namespace App\Tests\Controller;

use App\Controller\AccueilController;
use App\Entity\Pointage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class AccueilControllerTest extends TestCase {

    private AccueilController $controller;

    protected function setUp(): void {
        $this->controller = new AccueilController();
    }

    public function testCalculerDistance(): void {
        $method = $this->getPrivateMethod('calculerDistance');

        // Distance proche (dans le rayon)
        $distance = $method->invoke($this->controller,
                46.8210677, 1.674782,
                46.8210677, 1.674782
        );
        $this->assertLessThan(10, $distance);

        // Distance loin (Paris ~200km)
        $distanceLoin = $method->invoke($this->controller,
                46.8210677, 1.674782,
                48.8566, 2.3522
        );
        $this->assertGreaterThan(200000, $distanceLoin);
    }

    public function testIsGeolocalisationValide(): void {
        $method = $this->getPrivateMethod('isGeolocalisationValide');

        $requestAvecGeo = new Request([], ['latitude' => '46.82', 'longitude' => '1.67']);
        $this->assertTrue($method->invoke($this->controller, $requestAvecGeo));

        $requestSansGeo = new Request();
        $this->assertFalse($method->invoke($this->controller, $requestSansGeo));
    }

    public function testIsDansRayonAutorise(): void {
        $method = $this->getPrivateMethod('isDansRayonAutorise');

        $requestDansRayon = new Request([], [
            'latitude' => '46.8210677',
            'longitude' => '1.674782'
        ]);
        $this->assertTrue($method->invoke($this->controller, $requestDansRayon));

        $requestHorsRayon = new Request([], [
            'latitude' => '48.8566',
            'longitude' => '2.3522'
        ]);
        $this->assertFalse($method->invoke($this->controller, $requestHorsRayon));
    }

    private function getPrivateMethod(string $name): \ReflectionMethod {
        $method = new \ReflectionMethod(AccueilController::class, $name);
        $method->setAccessible(true);
        return $method;
    }
}
