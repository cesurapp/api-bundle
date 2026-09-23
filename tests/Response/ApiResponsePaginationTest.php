<?php

namespace Cesurapp\ApiBundle\Tests\Response;

use Cesurapp\ApiBundle\Doctrine\QueryPaginator;
use Cesurapp\ApiBundle\Tests\_App\Entity\Tag;
use Cesurapp\ApiBundle\Tests\_App\Entity\User;
use Cesurapp\ApiBundle\Tests\_App\Resources\UserResource;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagination (offset + cursor), filter, sort and export of an ApiResponse, against a real database.
 *
 * Users: 1 red, 2 red, 3 blue, 4 red, 5 (no team) — emails a1@x.test … a5@x.test.
 * Tags: user 1 → a, b, c; user 2 → d; user 4 → e, f.
 */
class ApiResponsePaginationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $schemaTool->updateSchema($em->getMetadataFactory()->getAllMetadata());

        $tags = [0 => ['a', 'b', 'c'], 1 => ['d'], 3 => ['e', 'f']];
        foreach (['red', 'red', 'blue', 'red', null] as $i => $team) {
            $user = new User();
            $user->setEmail(sprintf('a%d@x.test', $i + 1));
            $user->setTeam($team);
            foreach ($tags[$i] ?? [] as $name) {
                new Tag($user, $name);
            }
            $em->persist($user);
        }
        $em->flush();
        $em->clear();

        UserResource::$preloaded = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testOffsetPagination(): void
    {
        $body = $this->json('/v1/users?page=2');

        $this->assertSame([3, 4], array_column($body['data'], 'id'));
        $this->assertSame(['max' => 2, 'prev' => 1, 'next' => 3, 'current' => 2, 'total' => 5], $body['pager']);
        $this->assertSame(['success' => ['Listed']], $body['message']);
    }

    public function testPageAndMaxAreKeptInRange(): void
    {
        $this->assertSame(1, $this->json('/v1/users?page=0')['pager']['current']);
        $this->assertSame(1, $this->json('/v1/users?page=-3')['pager']['current']);
        $this->assertSame(2, $this->json('/v1/users?max=0')['pager']['max']);
        $this->assertSame(2, $this->json('/v1/users?max=-5')['pager']['max']);
        $this->assertSame(100, $this->json('/v1/users?max=5000')['pager']['max']);
        $this->assertSame([], $this->json('/v1/users?page=9223372036854775807')['data']);
    }

    /**
     * Equal team values are ordered by id, in the requested direction: OFFSET pages stay stable.
     */
    public function testSortHasIdTieBreaker(): void
    {
        $this->assertSame([5, 3, 1, 2, 4], array_column($this->json('/v1/users?sort_by=team&sort=ASC&max=100')['data'], 'id'));
        $this->assertSame([4, 2, 1, 3, 5], array_column($this->json('/v1/users?sort_by=team&sort=DESC&max=100')['data'], 'id'));
    }

    public function testFilter(): void
    {
        $this->assertSame([3], array_column($this->json('/v1/users?filter[email]=a3')['data'], 'id'));
        $this->assertSame([1, 2, 3, 4], array_column($this->json('/v1/users?max=100&filter[team][]=red&filter[team][]=blue')['data'], 'id'));
        $this->assertSame([2, 3], array_column($this->json('/v1/users?filter[createdRange][from]=2&filter[createdRange][to]=3')['data'], 'id'));
        $this->assertCount(5, $this->json('/v1/users?max=100&filter[createdRange]=5')['data']);
    }

    public function testFilterValueOfTheWrongShapeIsABadRequest(): void
    {
        $response = $this->request('/v1/users?filter[email][]=a1');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid value for filter "email".', json_decode($response->getContent(), true)['message']);
    }

    public function testFilterRejectingTheValueIsABadRequest(): void
    {
        $response = $this->request('/v1/users?filter[id]=abc');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid ID: "abc".', json_decode($response->getContent(), true)['message']);
    }

    public function testCursorPagination(): void
    {
        $page = $this->json('/v1/users/cursor');
        $this->assertSame([5, 4], array_column($page['data'], 'id'));
        $this->assertSame(['max' => 2, 'next' => '4', 'sort' => 'DESC'], $page['pager']);

        $page = $this->json('/v1/users/cursor?cursor=4');
        $this->assertSame([3, 2], array_column($page['data'], 'id'));
        $this->assertSame('2', $page['pager']['next']);

        $page = $this->json('/v1/users/cursor?cursor=2');
        $this->assertSame([1], array_column($page['data'], 'id'));
        $this->assertNull($page['pager']['next']);

        $page = $this->json('/v1/users/cursor?sort=ASC&cursor=3');
        $this->assertSame([4, 5], array_column($page['data'], 'id'));
    }

    public function testCursorPageIgnoresSortBy(): void
    {
        $this->assertSame([5, 4], array_column($this->json('/v1/users/cursor?sort_by=email&sort=DESC')['data'], 'id'));
    }

    public function testInvalidCursorIsABadRequest(): void
    {
        $this->assertSame(400, $this->request('/v1/users/cursor?cursor=abc')->getStatusCode());
    }

    public function testQueryObjectIsPaginatedAndNotFiltered(): void
    {
        $body = $this->json('/v1/users/query?filter[email]=a3&sort_by=email&page=2');

        $this->assertSame([3, 4], array_column($body['data'], 'id'));
    }

    public function testExportMatchesFieldsCaseInsensitively(): void
    {
        $csv = $this->export('/v1/users?export=csv&export_field[]=ID&export_field[]=EMAIL&filter[team][]=red');

        $this->assertSame(['ID,Email', '1,a1@x.test', '2,a2@x.test', '4,a4@x.test'], $csv);
    }

    public function testExportLimit(): void
    {
        $this->assertCount(3, $this->export('/v1/users/export-limited?export=csv')); // header + 2 rows
    }

    /**
     * Export belongs to paginated lists: on another endpoint ?export is ignored, not a TypeError.
     */
    public function testExportIsIgnoredOnANonPaginatedResponse(): void
    {
        $response = $this->request('/v1/users/3?export=csv');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => ['id' => 3, 'email' => 'a3@x.test', 'team' => 'blue', 'tags' => []], 'message' => ['info' => ['Found']]], json_decode($response->getContent(), true));
    }

    /**
     * A fetch-joined collection returns a SQL row per tag: the page still holds max whole users,
     * each with all of its tags, and the total counts users, not rows.
     */
    public function testFetchJoinedCollectionPagesWholeEntities(): void
    {
        $page = $this->json('/v1/users/with-tags');
        $this->assertSame([1, 2], array_column($page['data'], 'id'));
        $this->assertSame([['a', 'b', 'c'], ['d']], array_column($page['data'], 'tags'));
        $this->assertSame(['max' => 2, 'prev' => null, 'next' => 2, 'current' => 1, 'total' => 5], $page['pager']);

        $page = $this->json('/v1/users/with-tags?page=2');
        $this->assertSame([3, 4], array_column($page['data'], 'id'));
        $this->assertSame([[], ['e', 'f']], array_column($page['data'], 'tags'));

        $page = $this->json('/v1/users/with-tags?page=3');
        $this->assertSame([5], array_column($page['data'], 'id'));
        $this->assertNull($page['pager']['next']);
    }

    /**
     * Why a to-many join needs the distinct-id strategy: a plain LIMIT cuts SQL rows, not users.
     */
    public function testQueryPaginatorStrategies(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $query = $em->createQueryBuilder()->select('u', 't')->from(User::class, 'u')->leftJoin('u.tags', 't')->orderBy('u.id');

        $this->assertSame([1, 2, 3], array_map(static fn (User $u) => $u->getId(), new QueryPaginator(true)->items($query, 0, 3)));
        $this->assertSame(5, new QueryPaginator(true)->count($query));

        $em->clear();
        $this->assertSame([1], array_map(static fn (User $u) => $u->getId(), new QueryPaginator(false)->items($query, 0, 3)));

        // Without a join both strategies agree
        $plain = $em->createQueryBuilder()->select('u')->from(User::class, 'u')->where('u.team = :team')->setParameter('team', 'red')->orderBy('u.id');
        $this->assertSame([2, 4], array_map(static fn (User $u) => $u->getId(), new QueryPaginator(false)->items($plain, 1, 5)));
        $this->assertSame(3, new QueryPaginator(false)->count($plain));
        $this->assertSame([], new QueryPaginator(true)->items($plain, 10, 5));
    }

    /**
     * toIterable() cannot hydrate a fetch-joined collection: the export pages through it instead,
     * and every user is written once with all of its tags.
     */
    public function testExportOfAFetchJoinedCollection(): void
    {
        $csv = $this->export('/v1/users/with-tags?export=csv&export_field[]=id&export_field[]=tags');

        $this->assertSame(['ID,Tags', '1,a|b|c', '2,d', '3,', '4,e|f', '5,'], $csv);
    }

    public function testValueObjectsWithoutResource(): void
    {
        $body = $this->json('/v1/value-objects');

        $this->assertSame('warning', $body['type']);
        $this->assertSame('2020-01-02 03:04:05.000000', $body['at']['date']);
    }

    public function testPreloadReceivesAllItemsOnce(): void
    {
        $this->json('/v1/users?max=3');

        $this->assertCount(1, UserResource::$preloaded);
        $this->assertCount(3, UserResource::$preloaded[0]);
        $this->assertContainsOnlyInstancesOf(User::class, UserResource::$preloaded[0]);
    }

    private function request(string $uri): Response
    {
        return self::$kernel->handle(Request::create($uri));
    }

    private function json(string $uri): array
    {
        $response = $this->request($uri);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode($response->getContent(), true);
    }

    /**
     * @return list<string>
     */
    private function export(string $uri): array
    {
        $response = $this->request($uri);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();

        return array_values(array_filter(array_map('trim', explode("\n", (string) ob_get_clean()))));
    }
}
