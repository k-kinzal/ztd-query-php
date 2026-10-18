<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PublishTopic;
use App\Entity\{Board,Community,Topic,User};
use App\Form\TopicType;
use App\Repository\DiscussionRepository;
use App\Security\Access;
use App\Service\Publishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class WebController extends AbstractController
{
    #[Route('/', name:'home', methods:['GET'])]
    public function home(EntityManagerInterface $em): Response
    {
        return $this->render('community/index.html.twig', ['communities' => $em->getRepository(Community::class)->findBy([], ['name' => 'ASC'], 100)]);
    }
    #[Route('/communities/{id}', name:'community_show', methods:['GET'])]
    public function community(Community $community, EntityManagerInterface $em, Access $access, #[CurrentUser] ?User $user): Response
    {
        return $this->render('community/show.html.twig', ['community' => $community,'boards' => array_filter($em->getRepository(Board::class)->findBy(['community' => $community]), fn (Board $board) => $access->viewBoard($board, $user))]);
    }
    #[Route('/boards/{id}', name:'board_show', methods:['GET'])]
    public function board(Board $board, Request $request, DiscussionRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $board);
        return $this->render('discussion/board.html.twig', ['board' => $board,'topics' => $repository->page($board, max(1, $request->query->getInt('before', PHP_INT_MAX)))]);
    }
    #[Route('/topics/{id}', name:'topic_show', methods:['GET'])]
    public function topic(Topic $topic, Request $request, DiscussionRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $topic);
        return $this->render('discussion/topic.html.twig', ['topic' => $topic,'posts' => $repository->posts($topic, max(0, $request->query->getInt('after')))]);
    }
    #[Route('/manage/boards/{id}/topics/new', name:'topic_new', methods:['GET','POST'])]
    public function create(Board $board, Request $request, #[CurrentUser] User $user, Publishing $publishing): Response
    {
        $this->denyAccessUnlessGranted('PUBLISH', $board);
        $form = $this->createForm(TopicType::class, ['requestKey' => bin2hex(random_bytes(24))]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $input = $form->getData();
            $topic = $publishing->create($board, $user, new PublishTopic($input['title'], $input['body'], $input['requestKey']));
            return $this->redirectToRoute('topic_show', ['id' => $topic->id], 303);
        }
        return $this->render('discussion/new.html.twig', ['board' => $board,'form' => $form]);
    }
}
