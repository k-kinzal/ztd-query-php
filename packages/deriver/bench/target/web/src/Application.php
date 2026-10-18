<?php

declare(strict_types=1);

namespace Harbor;

use Harbor\Audit\AuditLog;
use Harbor\Community\Communities;
use Harbor\Database\Connection;
use Harbor\Discussion\{Discussions, Engagement, PublishingPolicy, Reading};
use Harbor\Http\{Problem, Request, Response, Router, View};
use Harbor\Moderation\Moderation;
use Harbor\Notification\Inbox;
use Harbor\Privacy\Privacy;
use Harbor\Security\{Access, Accounts, RateLimiter, Session};
use Harbor\Validation\Input;
use Throwable;

final class Application
{
    private readonly Router $router;

    public function __construct(Connection $database, private readonly Session $session, string $views)
    {
        $access = new Access($database);
        $audit = new AuditLog($database);
        $limiter = new RateLimiter($database);
        $accounts = new Accounts($database, $audit, $limiter);
        $communities = new Communities($database, $access, $audit);
        $discussions = new Discussions($database, $access, $audit, new PublishingPolicy());
        $reading = new Reading($database, $access);
        $moderation = new Moderation($database, $access, $audit);
        $engagement = new Engagement($database, $access);
        $inbox = new Inbox($database, $access);
        $privacy = new Privacy($database, $access, $audit);
        $view = new View($views);
        $this->router = $router = new Router();
        $user = fn (): int => $session->requireUser();
        $router->add('GET', '/', fn () => $view->render('home', ['communities' => $reading->communities(), 'csrf' => $session->csrf(), 'user' => $session->user()]));
        $router->add('GET', '/topics/{topic}', fn (Request $r, array $p) => $view->render('topic', ['topic' => $reading->thread($p['topic'], $session->user(), $r->cursor('after', 0)), 'csrf' => $session->csrf()]));
        $router->add('GET', '/health', function () use ($database): Response {
            $database->one('SELECT 1');
            return Response::json(['status' => 'ok']);
        });
        $router->add('GET', '/api/session', fn () => Response::json(['user' => $session->user(), 'csrf' => $session->csrf()]));
        $router->add('POST', '/api/register', function (Request $r) use ($accounts, $session, $limiter): Response {
            $limiter->consume('register:' . hash('sha256', $r->ip), 10, 3600);
            $id = $accounts->register($r->body);
            $session->login($id);
            return Response::json(['id' => $id, 'csrf' => $session->csrf()], 201);
        });
        $router->add('POST', '/api/login', function (Request $r) use ($accounts, $session): Response {
            $session->login($accounts->authenticate(Input::text($r->body, 'email', 3, 254), Input::text($r->body, 'password', 1, 200), $r->ip));
            return Response::json(['csrf' => $session->csrf()]);
        });
        $router->add('POST', '/api/logout', function () use ($session): Response {
            $session->logout();
            return Response::json(['csrf' => $session->csrf()]);
        });
        $router->add('PUT', '/api/password', function (Request $r) use ($accounts, $user): Response {
            $accounts->changePassword($user(), Input::text($r->body, 'current', 1, 200), Input::text($r->body, 'password', 12, 200));
            return Response::json(['changed' => true]);
        });
        $router->add('GET', '/api/communities', fn () => Response::json($reading->communities()));
        $router->add('POST', '/api/communities', fn (Request $r) => Response::json(['id' => $communities->create($user(), $r->body)], 201));
        $router->add('POST', '/api/communities/{community}/join', function (Request $r, array $p) use ($communities, $user): Response {
            $communities->join($p['community'], $user());
            return Response::json(['joined' => true]);
        });
        $router->add('PUT', '/api/communities/{community}/members/{member}', function (Request $r, array $p) use ($communities, $user): Response {
            $communities->changeRole($p['community'], $user(), $p['member'], Input::choice($r->body, 'role', ['member','moderator']));
            return Response::json(['updated' => true]);
        });
        $router->add('POST', '/api/communities/{community}/transfer', function (Request $r, array $p) use ($communities, $user): Response {
            $communities->transfer($p['community'], $user(), Input::positive($r->body, 'user'));
            return Response::json(['transferred' => true]);
        });
        $router->add('GET', '/api/communities/{community}/boards', fn (Request $r, array $p) => Response::json($reading->boards($p['community'], $session->user())));
        $router->add('POST', '/api/communities/{community}/boards', fn (Request $r, array $p) => Response::json(['id' => $communities->createBoard($p['community'], $user(), $r->body)], 201));
        $router->add('PUT', '/api/boards/{board}/archive', function (Request $r, array $p) use ($communities, $user): Response {
            if (!is_bool($r->body['archived'] ?? null)) {
                throw new Problem(422, 'archived must be a boolean.');
            }
            $communities->archiveBoard($p['board'], $user(), $r->body['archived']);
            return Response::json(['updated' => true]);
        });
        $router->add('GET', '/api/boards/{board}/topics', fn (Request $r, array $p) => Response::json($reading->topics($p['board'], $session->user(), $r->cursor('before', PHP_INT_MAX))));
        $router->add('GET', '/api/boards/{board}/search', fn (Request $r, array $p) => Response::json($reading->search($p['board'], $session->user(), Input::text($r->query, 'q', 2, 100), $r->cursor('before', PHP_INT_MAX))));
        $router->add('POST', '/api/boards/{board}/topics', fn (Request $r, array $p) => Response::json(['id' => $discussions->publish($p['board'], $user(), $r->body, $r->headers['idempotency-key'] ?? '')], 201));
        $router->add('GET', '/api/topics/{topic}', fn (Request $r, array $p) => Response::json($reading->thread($p['topic'], $session->user(), $r->cursor('after', 0))));
        $router->add('POST', '/api/topics/{topic}/replies', fn (Request $r, array $p) => Response::json(['id' => $discussions->reply($p['topic'], $user(), $r->body)], 201));
        $router->add('PUT', '/api/posts/{post}', function (Request $r, array $p) use ($discussions, $user): Response {
            $discussions->edit($p['post'], $user(), $r->body);
            return Response::json(['updated' => true]);
        });
        $router->add('POST', '/api/topics/{topic}/transition', function (Request $r, array $p) use ($discussions, $user): Response {
            $discussions->transition($p['topic'], $user(), Input::text($r->body, 'action', 3, 20), Input::positive($r->body, 'version'));
            return Response::json(['updated' => true]);
        });
        $router->add('POST', '/api/posts/{post}/reports', fn (Request $r, array $p) => Response::json(['id' => $moderation->report($p['post'], $user(), Input::text($r->body, 'reason', 10, 2000))], 201));
        $router->add('GET', '/api/communities/{community}/reports', fn (Request $r, array $p) => Response::json($moderation->queue($p['community'], $user())));
        $router->add('PUT', '/api/reports/{report}', function (Request $r, array $p) use ($moderation, $user): Response {
            $moderation->resolve($p['report'], $user(), Input::text($r->body, 'decision', 3, 20), Input::text($r->body, 'reason', 10, 2000));
            return Response::json(['resolved' => true]);
        });
        $router->add('POST', '/api/communities/{community}/sanctions', fn (Request $r, array $p) => Response::json(['id' => $moderation->sanction($p['community'], $user(), Input::positive($r->body, 'user'), Input::text($r->body, 'reason', 10, 2000), Input::positive($r->body, 'hours'))], 201));
        $router->add('PUT', '/api/topics/{topic}/subscription', function (Request $r, array $p) use ($engagement, $user): Response {
            $engagement->subscribe($p['topic'], $user(), true);
            return Response::json(['subscribed' => true]);
        });
        $router->add('DELETE', '/api/topics/{topic}/subscription', function (Request $r, array $p) use ($engagement, $user): Response {
            $engagement->subscribe($p['topic'], $user(), false);
            return Response::json(['subscribed' => false]);
        });
        $router->add('PUT', '/api/topics/{topic}/bookmark', function (Request $r, array $p) use ($engagement, $user): Response {
            $engagement->bookmark($p['topic'], $user(), true);
            return Response::json(['bookmarked' => true]);
        });
        $router->add('DELETE', '/api/topics/{topic}/bookmark', function (Request $r, array $p) use ($engagement, $user): Response {
            $engagement->bookmark($p['topic'], $user(), false);
            return Response::json(['bookmarked' => false]);
        });
        $router->add('PUT', '/api/posts/{post}/reaction', function (Request $r, array $p) use ($engagement, $user): Response {
            $engagement->react($p['post'], $user(), Input::text($r->body, 'reaction', 3, 20));
            return Response::json(['reacted' => true]);
        });
        $router->add('GET', '/api/notifications', fn (Request $r) => Response::json($inbox->list($user(), $r->cursor('before', PHP_INT_MAX))));
        $router->add('PUT', '/api/notifications/{notification}', function (Request $r, array $p) use ($inbox, $user): Response {
            $inbox->markRead($p['notification'], $user());
            return Response::json(['read' => true]);
        });
        $router->add('GET', '/api/account/export', fn () => Response::json($privacy->export($user())));
        $router->add('DELETE', '/api/account', function (Request $r) use ($privacy, $user, $session): Response {
            $privacy->erase($user(), Input::text($r->body, 'password', 1, 200));
            $session->logout();
            return Response::json(['erased' => true]);
        });
    }

    public function handle(Request $request): Response
    {
        try {
            if (!in_array($request->method, ['GET','HEAD','OPTIONS'], true)) {
                $this->session->verify($request->headers['x-csrf-token'] ?? $request->body['_token'] ?? '');
            }
            return $this->router->dispatch($request);
        } catch (Problem $problem) {
            return Response::json(['error' => $problem->getMessage()], $problem->status);
        } catch (Throwable $error) {
            $reference = bin2hex(random_bytes(8));
            error_log('Harbor failure ' . $reference . ': ' . $error);
            return Response::json(['error' => 'An internal error occurred.', 'reference' => $reference], 500);
        }
    }
}
