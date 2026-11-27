<?php

declare(strict_types=1);

namespace App\Projects;

use App\Security\ProjectPolicy;
use App\Shared\Exception\NotFoundException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\Input;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Users\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProjectController
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectPolicy $policy,
        private readonly JsonResponder $json,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->currentUser($request);
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->getQueryParams()['per_page'] ?? 20)));
        $status = $request->getQueryParams()['status'] ?? null;
        $status = is_string($status) && in_array($status, [Project::STATUS_ACTIVE, Project::STATUS_ARCHIVED], true) ? $status : null;

        $result = $this->projects->findByOwner($user->id, $page, $perPage, $status);

        return $this->json->paginated(
            $response,
            array_map(static fn (Project $project): array => $project->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    /** @param array<string, string> $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $project = $this->project($args['id'] ?? '');
        $this->assertCan($request, 'view', $project);

        return $this->json->success($response, $project->toArray());
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->currentUser($request);
        $in = Input::fromRequest($request);
        $values = [
            'title' => $in->requiredString('title', 200),
            'research_question' => $in->optionalString('research_question', 5000),
            'discipline' => $in->optionalString('discipline', 160),
            'language' => $in->optionalLocale('language') ?? 'en',
        ];
        $in->validate();

        $project = $this->projects->create($user->id, $values);

        return $this->json->created($response, $project->toArray());
    }

    /** @param array<string, string> $args */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $project = $this->project($args['id'] ?? '');
        $this->assertCan($request, 'edit', $project);
        $in = Input::fromRequest($request);
        $values = [];
        if ($in->has('title')) {
            $values['title'] = $in->requiredString('title', 200);
        }
        if ($in->has('research_question')) {
            $values['research_question'] = $in->optionalString('research_question', 5000);
        }
        if ($in->has('discipline')) {
            $values['discipline'] = $in->optionalString('discipline', 160);
        }
        if ($in->has('language')) {
            $values['language'] = $in->optionalLocale('language');
        }
        if ($in->has('status')) {
            $values['status'] = $in->oneOf('status', [Project::STATUS_ACTIVE, Project::STATUS_ARCHIVED]);
        }
        $in->validate();

        $project = $this->projects->update($project, $values);

        return $this->json->success($response, $project->toArray());
    }

    /** @param array<string, string> $args */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $project = $this->project($args['id'] ?? '');
        $this->assertCan($request, 'delete', $project);
        $this->projects->softDelete($project);

        return $this->json->noContent($response);
    }

    private function project(string $uuid): Project
    {
        return $this->projects->findByUuid($uuid) ?? throw new NotFoundException();
    }

    private function assertCan(ServerRequestInterface $request, string $ability, Project $project): void
    {
        if (!$this->policy->can($this->currentUser($request), $ability, $project)) {
            throw new NotFoundException();
        }
    }

    private function currentUser(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(RequestAttributes::USER);

        return $user instanceof User ? $user : throw new UnauthorizedException();
    }
}
