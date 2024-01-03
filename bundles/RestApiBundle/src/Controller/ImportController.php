<?php

namespace RestApiBundle\Controller;

use RestApiBundle\Middleware\TokenValidationMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ImportController
{
    /**
     * @Route("/import-products", name="importProducts", methods={"GET"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function importProducts(Request $request, TokenValidationMiddleware $tokenValidation): JsonResponse
    {
        $products = $request->query->get('sheet-name');
        $language = $request->query->get('locale');

        $response = $tokenValidation->handleValidation($request);
        if ($response !== null) {
            return $response;
        }

        $consolePath = PIMCORE_PROJECT_ROOT . '/bin/console';

        $command = [
            'php',
            $consolePath,
            'import:products',
            $products,
            $language
        ];

        $process = new Process($command);
        $process->setWorkingDirectory(PIMCORE_PROJECT_ROOT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();

            return new JsonResponse([
                'message' => $output,
                'success' => true,
                'error' => null
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => null,
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
