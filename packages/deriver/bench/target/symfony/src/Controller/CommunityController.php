<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\{CreateBoard,CreateCommunity};
use App\Entity\{Board,Community,User};
use App\Http\Representation;
use App\Security\Access;
use App\Service\CommunityManagement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/communities')]
final class CommunityController extends AbstractController
{
    public function __construct(private readonly Representation $view)
    {
    }
    #[Route('', methods:['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        return $this->json(array_map($this->view->community(...), $em->getRepository(Community::class)->findBy([], ['id' => 'ASC'], 100)));
    }
    #[Route('', methods:['POST'], format:'json')]
    public function create(#[MapRequestPayload] CreateCommunity $input, #[CurrentUser] User $user, CommunityManagement $service): JsonResponse
    {
        return $this->json($this->view->community($service->create($user, $input)), 201);
    }
    #[Route('/{id}/join', methods:['POST'])]
    public function join(Community $community, #[CurrentUser] User $user, CommunityManagement $service): Response
    {
        $service->join($community, $user);
        return new Response('', 204);
    }
    #[Route('/{id}/boards', methods:['GET'])]
    public function boards(Community $community, #[CurrentUser] ?User $user, EntityManagerInterface $em, Access $access): JsonResponse
    {
        return $this->json(array_map($this->view->board(...), array_values(array_filter($em->getRepository(Board::class)->findBy(['community' => $community], ['id' => 'ASC']), fn (Board $board) => $access->viewBoard($board, $user)))));
    }
    #[Route('/{id}/boards', methods:['POST'], format:'json')]
    public function board(Community $community, #[MapRequestPayload] CreateBoard $input, #[CurrentUser] User $user, CommunityManagement $service): JsonResponse
    {
        return $this->json($this->view->board($service->board($community, $user, $input)), 201);
    }
    #[Route('/{id}/transfer', methods:['POST'])]
    public function transfer(Community $community, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, CommunityManagement $service): Response
    {
        $successor = $em->find(User::class, (int)($request->toArray()['user_id'] ?? 0)) ?? throw $this->createNotFoundException();
        $service->transfer($community, $user, $successor);
        return new Response('', 204);
    }

}
