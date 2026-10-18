<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\{FileReport,ResolveReport,RestrictMember};
use App\Entity\{Community,Post,Report,User};
use App\Http\Representation;
use App\Service\Moderation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse};
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class ModerationController extends AbstractController
{
    public function __construct(private readonly Representation $view)
    {
    }
    #[Route('/posts/{id}/reports', methods:['POST'], format:'json')]
    public function report(Post $post, #[MapRequestPayload] FileReport $input, #[CurrentUser] User $user, Moderation $service): JsonResponse
    {
        return $this->json($this->view->report($service->report($post, $user, $input)), 201);
    }
    #[Route('/reports/{id}', methods:['PUT'], format:'json')]
    public function resolve(Report $report, #[MapRequestPayload] ResolveReport $input, #[CurrentUser] User $user, Moderation $service): JsonResponse
    {
        $service->resolve($report, $user, $input);
        return $this->json($this->view->report($report));
    }
    #[Route('/communities/{id}/reports', methods:['GET'])]
    public function queue(Community $community, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('MODERATE', $community);
        $reports = $em->createQueryBuilder()->select('r')->from(Report::class, 'r')->join('r.post', 'p')->join('p.topic', 't')->join('t.board', 'b')->where('b.community=:community AND r.status=:status')->setParameter('community', $community)->setParameter('status', 'open')->orderBy('r.id', 'ASC')->setMaxResults(100)->getQuery()->getResult();
        return $this->json(array_map($this->view->report(...), $reports));
    }
    #[Route('/communities/{id}/restrictions', methods:['POST'], format:'json')]
    public function restrict(Community $community, #[MapRequestPayload] RestrictMember $input, #[CurrentUser] User $user, Moderation $service): JsonResponse
    {
        return $this->json(['id' => $service->restrict($community, $user, $input)->id], 201);
    }
}
