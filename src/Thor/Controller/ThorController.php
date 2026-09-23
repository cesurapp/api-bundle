<?php

namespace Cesurapp\ApiBundle\Thor\Controller;

use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Cesurapp\ApiBundle\Thor\Generator\TypeScriptGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public API documentation.
 *
 * Outside debug the page and the client archive are built once per deploy (cache:clear empties
 * kernel.cache_dir) and then served from disk: a public URL must not run route reflection and
 * archive building on every hit.
 */
class ThorController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.cache_dir%')] private readonly string $cacheDir,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
    }

    /**
     * View Thor API Documentation.
     */
    #[Route(path: '/thor', name: 'thor.view', methods: ['GET'])]
    #[Thor(title: 'Thor Api Documentation', isHidden: true, isAuth: false)]
    public function view(ThorExtractor $extractor): Response
    {
        $html = $this->cached('index.html', static function (string $file) use ($extractor) {
            file_put_contents($file, $extractor->render());
        });

        return new Response((string) file_get_contents($html), headers: ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Download TypeScript Api.
     */
    #[Route(path: '/thor/download', name: 'thor.download', methods: ['GET'])]
    #[Thor(title: 'Thor Api Download', isHidden: true, isAuth: false)]
    public function download(ThorExtractor $extractor): Response
    {
        $archive = $this->cached('Api.tar.gz', static function (string $file) use ($extractor) {
            new TypeScriptGenerator($extractor->extractData(true))->generate()->compress(dirname($file), basename($file));
        });

        return ApiResponse::downloadFile($archive, 'Api.tar.gz');
    }

    /**
     * Path of a file under kernel.cache_dir/thor, built by $build when missing — or always in debug,
     * where routes change without a cache clear. $build writes a temporary name that is then renamed
     * into place, so a concurrent request never serves a half written file.
     */
    private function cached(string $name, \Closure $build): string
    {
        $dir = $this->cacheDir.'/thor';
        $file = $dir.'/'.$name;

        if (!$this->debug && is_file($file)) {
            return $file;
        }

        $fs = new Filesystem();
        $fs->mkdir($dir);

        $tmp = $dir.'/tmp_'.bin2hex(random_bytes(6));
        $fs->mkdir($tmp);
        try {
            $build($tmp.'/'.$name);
            $fs->rename($tmp.'/'.$name, $file, true);
        } finally {
            $fs->remove($tmp);
        }

        return $file;
    }
}
