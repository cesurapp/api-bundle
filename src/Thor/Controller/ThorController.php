<?php

namespace Cesurapp\ApiBundle\Thor\Controller;

use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Cesurapp\ApiBundle\Thor\Generator\TypeScriptGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ThorController extends AbstractController
{
    /**
     * View Thor API Documentation.
     */
    #[Route(path: '/thor', name: 'thor.view')]
    #[Thor(title: 'Thor Api Documentation', isHidden: true, isAuth: false)]
    public function view(ThorExtractor $extractor): Response
    {
        return (new Response())->setContent($extractor->render());
    }

    /**
     * Download TypeScript Api.
     */
    #[Route(path: '/thor/download', name: 'thor.download')]
    #[Thor(title: 'Thor Api Download', isHidden: true, isAuth: false)]
    public function download(ThorExtractor $extractor): Response
    {
        $tsGenerator = new TypeScriptGenerator($extractor->extractData(true));

        return ApiResponse::downloadFile(
            $tsGenerator
                ->generate()
                ->compress($this->getParameter('kernel.cache_dir'))
        );
    }
}
