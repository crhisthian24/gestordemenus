<?php

namespace App\Controller;

use App\Entity\Empresa;
use App\Service\SftpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SftpController extends AbstractController
{
    #[Route('/empresa/{id}/archivos', name: 'empresa_archivos')]
    public function listarArchivos(Empresa $empresa, SftpService $sftpService): Response
    {
        $filesystem = $sftpService->connect($empresa);

        $files = $filesystem->listContents('/', false);

        return $this->render('sftp/list.html.twig', [
            'empresa' => $empresa,
            'files' => $files,
        ]);
    }
}