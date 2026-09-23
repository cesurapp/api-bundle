<?php

namespace Cesurapp\ApiBundle\Response\Traits;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait FileDownloadTrait
{
    /**
     * Download Binary File.
     *
     * Preferred for any size: streams from disk, supports Range requests and X-Sendfile.
     */
    public static function downloadFile(
        \SplFileInfo|string $path,
        string $fileName = '',
        string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT,
    ): BinaryFileResponse {
        return new BinaryFileResponse($path)->setContentDisposition($disposition, $fileName);
    }

    /**
     * Download Large File.
     *
     * @deprecated use downloadFile(): BinaryFileResponse streams large files as well and adds Range / X-Sendfile support
     */
    public static function downloadFileLarge(string $filePath, ?string $fileName = null): StreamedResponse
    {
        $file = new File($filePath);
        $fileName ??= $file->getFilename();

        return new StreamedResponse(static function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            if (false === $handle) {
                throw new \RuntimeException(sprintf('File "%s" could not be opened.', $filePath));
            }

            $output = fopen('php://output', 'wb');
            if (false === $output) {
                fclose($handle);

                throw new \RuntimeException('The output stream could not be opened.');
            }

            try {
                while (!feof($handle)) {
                    fwrite($output, (string) fread($handle, 1024 * 1024));
                    flush();
                }
            } finally {
                fclose($output);
                fclose($handle);
            }
        }, 200, [
            'Content-Type' => $file->getMimeType() ?? 'application/octet-stream',
            'Content-Length' => (string) $file->getSize(),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName,
                self::asciiFallback($fileName),
            ),
        ]);
    }

    /**
     * ASCII stand-in for a non-ASCII file name ("şubat_raporu.pdf" → "subat_raporu.pdf");
     * the real name still travels as filename*=UTF-8''….
     */
    private static function asciiFallback(string $fileName): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fileName) : '';
        $ascii = preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/', '_', '' !== $ascii ? $ascii : $fileName);

        return '' !== trim((string) $ascii, '_ .') ? (string) $ascii : 'download';
    }
}
