<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use App\Entity\Modification;

class AdminUtilisateursController extends AbstractController
{
    private const ROLES_AUTORISES = ['ROLE_USER', 'ROLE_ADMIN'];
    private const TYPE_USER = 'user';
    private const ROUTE_ADMIN_UTILISATEURS = 'admin.utilisateurs';
    private const JSON_REPONSE_SUCCESS = 'success';
    private const JSON_REPONSE_MESSAGE = 'message';
    private const MSG_CSRF_INVALIDE = 'Token CSRF invalide.';
    private const MSG_MDP_INCORRECT = 'Mot de passe administrateur incorrect.';
    private const MSG_USER_INTRUVABLE = 'Utilisateur introuvable.';
    private const MSG_SUPPR_PROPRE_COMPTE = 'Vous ne pouvez pas supprimer votre propre compte.';
    private const CLE_LOG_USERNAME = 'username';
    private const CLE_LOG_NOM = 'nom';
    private const CLE_LOG_PRENOM = 'prenom';
    private const CLE_LOG_ROLES = 'roles';
    private const CLE_LOG_IS_ACTIVE = 'isActive';
    private const CLE_LOG_PASSWORD = 'password';
    private const ACTION_CREATION = 'CREATION_UTILISATEUR';
    private const ACTION_SUPPRESSION = 'SUPPRESSION_UTILISATEUR';
    private const ACTION_CHANGE_PASSWORD = 'CHANGE_PASSWORD';
    private const ACTION_USER_DESACTIVE = 'USER_DESACTIVE';
    private const ACTION_USER_ACTIVE = 'USER_ACTIVE';
    private const JSON_CLE_USER_ID = 'user_id';
    private const JSON_CLE_ADMIN_PASSWORD = 'admin_password';
    private const JSON_CLE_TOKEN = '_token';
    private const CSRF_CONTEXT_DELETE_USER = 'delete_user';
    private const CSRF_CONTEXT_CHANGE_PASSWORD = 'change_password';
    private const CSRF_CONTEXT_TOGGLE_USER = 'toggle_user_';

    #[Route('/admin/utilisateurs', name: 'admin.utilisateurs')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $filterStatus = $request->query->get('status', 'active');

        if ($filterStatus === 'active') {
            $users = $userRepository->findBy(['isActive' => true]);
        } elseif ($filterStatus === 'inactive') {
            $users = $userRepository->findBy(['isActive' => false]);
        } else {
            $users = $userRepository->findAll();
        }

        return $this->render('admin/admin.utilisateurs.html.twig', [
                    'users' => $users,
                    'filterStatus' => $filterStatus,
        ]);
    }

    #[Route('/admin/utilisateurs/add', name: 'admin.utilisateurs.add', methods: ['POST'])]
    public function add(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): RedirectResponse {
        $donnees = $this->validerCreationUtilisateur($request, $em);

        if ($donnees === null) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
        }

        $user = $this->creerUtilisateur($donnees, $passwordHasher);
        $em->persist($user);
        $em->flush();

        $this->loggerCreation($em, $user);

        return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
    }

    private function validerCreationUtilisateur(Request $request, EntityManagerInterface $em): ?array
    {
        $donnees = $this->extraireDonneesFormulaire($request);

        $erreurs = $this->validerDonneesCreation($donnees);
        if (!empty($erreurs)) {
            foreach ($erreurs as $message) {
                $this->addFlash('error', $message);
            }
            return null;
        }

        if (!$this->utilisateurExistant($em, $donnees['username'])) {
            return null;
        }

        return $donnees;
    }

    private function extraireDonneesFormulaire(Request $request): array
    {
        $nom = trim($request->request->get('nom', ''));
        $prenom = trim($request->request->get('prenom', ''));

        // Génération automatique du username (nom.prenom)
        $username = strtolower($nom . ' ' . $prenom);

        return [
            'username' => $username,
            'nom' => $nom,
            'prenom' => $prenom,
            'plainPassword' => $request->request->get('password'),
            'passwordConfirm' => $request->request->get('password_confirm'),
            'role' => $request->request->get('role', 'ROLE_USER'),
        ];
    }

    private function validerDonneesCreation(array $donnees): array
    {
        $erreurs = [];

        if (!in_array($donnees['role'], self::ROLES_AUTORISES, true)) {
            $erreurs[] = 'Rôle invalide.';
        }

        if (!$donnees['nom'] || !$donnees['prenom'] || !$donnees['plainPassword']) {
            $erreurs[] = 'Nom, prénom et mot de passe sont requis.';
        }

        if ($donnees['plainPassword'] !== $donnees['passwordConfirm']) {
            $erreurs[] = 'Les mots de passe ne correspondent pas.';
        }

        return $erreurs;
    }

    private function utilisateurExistant(EntityManagerInterface $em, string $username): bool
    {
        $existingUser = $em->getRepository(User::class)
                ->findOneBy([self::CLE_LOG_USERNAME => $username]);

        if ($existingUser) {
            return false;
        }

        return true;
    }

    private function creerUtilisateur(array $donnees, UserPasswordHasherInterface $passwordHasher): User
    {
        $user = new User();
        $user->setUsername($donnees['username']);
        $user->setNom($donnees['nom']);
        $user->setPrenom($donnees['prenom']);
        $user->setPassword($passwordHasher->hashPassword($user, $donnees['plainPassword']));
        $user->setRoles([$donnees['role']]);

        return $user;
    }

    private function loggerCreation(EntityManagerInterface $em, User $user): void
    {
        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_USER);
        $log->setAction(self::ACTION_CREATION);
        $log->setObjetId($user->getId());
        $log->setAnciennesDonnees(null);
        $log->setNouvellesDonnees([
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_NOM => $user->getNom(),
            self::CLE_LOG_PRENOM => $user->getPrenom(),
            self::CLE_LOG_ROLES => $user->getRoles(),
            self::CLE_LOG_IS_ACTIVE => $user->isActive(),
        ]);

        $em->persist($log);
        $em->flush();
    }

    #[Route('/admin/utilisateurs/delete', name: 'admin.utilisateurs.delete', methods: ['POST'])]
    public function deleteUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $resultatValidation = $this->validerSuppressionUtilisateur($request, $em, $security, $passwordHasher);
        if ($resultatValidation === false) {
            return $this->jsonError('Erreur de validation');
        }

        $this->effectuerSuppression($em, $resultatValidation['user']);
        return $this->json([self::JSON_REPONSE_SUCCESS => true]);
    }

    private function validerSuppressionUtilisateur(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        UserPasswordHasherInterface $passwordHasher
    ): array|false {
        $data = json_decode($request->getContent(), true);
        $userId = $data[self::JSON_CLE_USER_ID] ?? null;
        $adminPassword = $data[self::JSON_CLE_ADMIN_PASSWORD] ?? '';
        $token = $data[self::JSON_CLE_TOKEN] ?? '';

        $erreurs = [];

        // CSRF
        if (!$this->isCsrfTokenValid(self::CSRF_CONTEXT_DELETE_USER, $token)) {
            $erreurs[] = self::MSG_CSRF_INVALIDE;
        }

        // Admin password
        $admin = $security->getUser();
        if (!$admin || !$passwordHasher->isPasswordValid($admin, $adminPassword)) {
            $erreurs[] = self::MSG_MDP_INCORRECT;
        }

        // User existe
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            $erreurs[] = self::MSG_USER_INTRUVABLE;
        }

        // Auto-suppression
        if ($user && $user->getId() === $admin->getId()) {
            $erreurs[] = self::MSG_SUPPR_PROPRE_COMPTE;
        }

        // Erreur trouvée
        if (!empty($erreurs)) {
            return false;
        }

        return ['user' => $user];
    }

    private function effectuerSuppression(EntityManagerInterface $em, User $user): void
    {
        $oldData = [
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_NOM => $user->getNom(),
            self::CLE_LOG_PRENOM => $user->getPrenom(),
            self::CLE_LOG_ROLES => $user->getRoles(),
            self::CLE_LOG_IS_ACTIVE => $user->isActive(),
        ];

        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_USER);
        $log->setAction(self::ACTION_SUPPRESSION);
        $log->setObjetId($user->getId());
        $log->setAnciennesDonnees($oldData);
        $log->setNouvellesDonnees(null);

        $em->persist($log);
        $em->remove($user);
        $em->flush();
    }

    #[Route('/admin/utilisateurs/toggle/{id}', name: 'admin.utilisateurs.toggle', methods: ['POST'])]
    public function toggle(
        User $user,
        EntityManagerInterface $em,
        Request $request,
        Security $security
    ): RedirectResponse {
        if (!$this->validerToggleUtilisateur($user, $request, $security)) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
        }

        $currentUser = $this->getUser();
        if ($currentUser && $currentUser->getId() === $user->getId()) {
            $this->get('security.token_storage')->setToken(null);
            $this->get('session')->invalidate();
            $this->effectuerToggle($em, $user);
            $em->flush();
            return $this->redirectToRoute('app_login');
        }

        $this->effectuerToggle($em, $user);
        $em->flush();

        return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
    }

    private function validerToggleUtilisateur(User $user, Request $request, Security $security): bool
    {
        $submittedToken = $request->request->get(self::JSON_CLE_TOKEN);

        if (!$this->isCsrfTokenValid(self::CSRF_CONTEXT_TOGGLE_USER . $user->getId(), $submittedToken)) {
            return false;
        }

        $currentUser = $security->getUser();
        if ($user->getId() === $currentUser->getId()) {
            return false;
        }
        return true;
    }

    private function effectuerToggle(EntityManagerInterface $em, User $user): void
    {
        $oldStatus = $user->isActive();
        $action = $oldStatus ? self::ACTION_USER_DESACTIVE : self::ACTION_USER_ACTIVE;

        $oldData = [
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_IS_ACTIVE => $oldStatus,
        ];

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $newData = [
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_IS_ACTIVE => $user->isActive(),
        ];

        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_USER);
        $log->setAction($action);
        $log->setObjetId($user->getId());
        $log->setAnciennesDonnees($oldData);
        $log->setNouvellesDonnees($newData);

        $em->persist($log);
        $em->flush();
    }

    #[Route('/admin/utilisateurs/password', name: 'admin.utilisateurs.password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(
            self::CSRF_CONTEXT_CHANGE_PASSWORD,
            $request->request->get(self::JSON_CLE_TOKEN)
        )) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
        }

        $donnees = $this->validerChangementMotDePasse($request, $userRepository);
        if ($donnees === null) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
        }

        $user = $userRepository->find($donnees['userId']);
        $oldData = [
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_NOM => $user->getNom(),
            self::CLE_LOG_PRENOM => $user->getPrenom(),
            self::CLE_LOG_ROLES => $user->getRoles(),
            self::CLE_LOG_IS_ACTIVE => $user->isActive(),
        ];

        $user->setPassword($passwordHasher->hashPassword($user, $donnees['password']));
        $em->flush();
        $this->loggerChangementMotDePasse($em, $user, $oldData);

        return $this->redirectToRoute(self::ROUTE_ADMIN_UTILISATEURS);
    }

    private function validerChangementMotDePasse(Request $request, UserRepository $userRepository): ?array
    {
        $userId = $request->request->get(self::JSON_CLE_USER_ID);
        $password = $request->request->get('password');
        $passwordConfirm = $request->request->get('password_confirm');

        if ($password !== $passwordConfirm) {
            return null;
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return null;
        }
        return ['userId' => $userId, 'password' => $password];
    }

    private function loggerChangementMotDePasse(EntityManagerInterface $em, User $user, array $oldData): void
    {
        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_USER);
        $log->setAction(self::ACTION_CHANGE_PASSWORD);
        $log->setObjetId($user->getId());
        $log->setAnciennesDonnees($oldData);
        $log->setNouvellesDonnees([
            self::CLE_LOG_USERNAME => $user->getUsername(),
            self::CLE_LOG_NOM => $user->getNom(),
            self::CLE_LOG_PRENOM => $user->getPrenom(),
            self::CLE_LOG_ROLES => $user->getRoles(),
            self::CLE_LOG_IS_ACTIVE => $user->isActive(),
            self::CLE_LOG_PASSWORD => '[MASQUÉ]'
        ]);

        $em->persist($log);
        $em->flush();
    }

    private function jsonError(string $message): JsonResponse
    {
        return $this->json([
                    self::JSON_REPONSE_SUCCESS => false,
                    self::JSON_REPONSE_MESSAGE => $message
        ]);
    }
}
