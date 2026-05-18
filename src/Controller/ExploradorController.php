<?php

namespace App\Controller;

use App\Entity\ArchivoLog;
use App\Entity\Empresa;
use App\Form\UploadPdfType;
use App\Repository\ArchivoLogRepository;
use App\Service\SftpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ExploradorController extends AbstractController
{
    #[Route('/explorador/{id}', name: 'app_explorador')]
    public function index(
        Empresa $empresa,
        SftpService $sftpService
    ): Response {
        $filesystem = $sftpService->connect(
            $empresa->getHost(),
            $empresa->getPuerto(),
            $empresa->getUsuario(),
            $empresa->getPassword(),
            $empresa->getRutaRemota()
        );

        $todos = $filesystem->listContents('.')->toArray();

        $archivos = array_filter($todos, function ($item) {
            return $item->type() === 'file'
                && strtolower(pathinfo($item->path(), PATHINFO_EXTENSION)) === 'pdf';
        });

        return $this->render('explorador/index.html.twig', [
            'empresa'  => $empresa,
            'archivos' => array_values($archivos),
        ]);
    }

    #[Route('/explorador/{id}/upload', name: 'app_upload_pdf')]
    public function upload(
        Empresa $empresa,
        Request $request,
        SftpService $sftpService,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(UploadPdfType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pdf = $form->get('pdf')->getData();

            $filesystem = $sftpService->connect(
                $empresa->getHost(),
                $empresa->getPuerto(),
                $empresa->getUsuario(),
                $empresa->getPassword(),
                $empresa->getRutaRemota()
            );

            $nombreOriginal   = $pdf->getClientOriginalName();
            $nombreSanitizado = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $nombreOriginal);
            $archivoBackup    = null;

            if ($filesystem->fileExists($nombreSanitizado)) {
                $fecha         = date('Y-m-d_H-i-s');
                $archivoBackup = pathinfo($nombreSanitizado, PATHINFO_FILENAME)
                    . '_' . $fecha . '.pdf';

                $contenidoViejo = $filesystem->read($nombreSanitizado);
                $filesystem->write($archivoBackup, $contenidoViejo);
                $filesystem->delete($nombreSanitizado);
            }

            $stream = fopen($pdf->getPathname(), 'r');
            $filesystem->writeStream($nombreSanitizado, $stream);
            fclose($stream);

            $log = new ArchivoLog();
            $log->setEmpresa($empresa->getNombre());
            $log->setUsuario($this->getUser()?->getUserIdentifier() ?? 'anonimo');
            $log->setArchivoOriginal($nombreSanitizado);
            $log->setArchivoBackup($archivoBackup);
            $log->setAccion($archivoBackup ? 'subida_con_backup' : 'subida_nueva');
            $log->setCreatedAt(new \DateTimeImmutable());

            $em->persist($log);
            $em->flush();

            $this->addFlash('success', 'PDF subido correctamente: ' . $nombreSanitizado);

            return $this->redirectToRoute('app_explorador', ['id' => $empresa->getId()]);
        }

        return $this->render('explorador/upload.html.twig', [
            'form'    => $form->createView(),
            'empresa' => $empresa,
        ]);
    }

    #[Route('/explorador/{id}/tamano/{filename}', name: 'app_tamano_pdf', requirements: ['filename' => '.+'])]
    public function tamano(
        Empresa $empresa,
        string $filename,
        SftpService $sftpService
    ): Response {
        try {
            $filesystem = $sftpService->connect(
                $empresa->getHost(),
                $empresa->getPuerto(),
                $empresa->getUsuario(),
                $empresa->getPassword(),
                $empresa->getRutaRemota()
            );

            $nombreSanitizado = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $filename);

            if ($filesystem->fileExists($nombreSanitizado)) {
                return $this->json(['existe' => true, 'tamano' => $filesystem->fileSize($nombreSanitizado)]);
            }

            return $this->json(['existe' => false, 'tamano' => 0]);

        } catch (\Exception $e) {
            return $this->json(['existe' => false, 'tamano' => 0]);
        }
    }

    #[Route('/explorador/{id}/historial', name: 'app_historial')]
    public function historial(
        Empresa $empresa,
        ArchivoLogRepository $logRepo
    ): Response {
        $logs = $logRepo->findByEmpresa($empresa->getNombre());

        return $this->render('explorador/historial.html.twig', [
            'empresa' => $empresa,
            'logs'    => $logs,
        ]);
    }

    #[Route('/explorador/{id}/delete/{filename}', name: 'app_delete_pdf', requirements: ['filename' => '.+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Empresa $empresa,
        string $filename,
        Request $request,
        SftpService $sftpService,
        EntityManagerInterface $em,
        ArchivoLogRepository $logRepo
    ): Response {
        if (!$this->isCsrfTokenValid('delete_' . $filename, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_explorador', ['id' => $empresa->getId()]);
        }

        try {
            $filesystem = $sftpService->connect(
                $empresa->getHost(),
                $empresa->getPuerto(),
                $empresa->getUsuario(),
                $empresa->getPassword(),
                $empresa->getRutaRemota()
            );

            if ($filesystem->fileExists($filename)) {
                $filesystem->delete($filename);
            }

            $logs = $logRepo->findByEmpresaYArchivo($empresa->getNombre(), $filename);
            foreach ($logs as $log) {
                $em->remove($log);
            }
            $em->flush();

            $this->addFlash('success', 'Archivo "' . $filename . '" eliminado correctamente.');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_explorador', ['id' => $empresa->getId()]);
    }

    #[Route('/explorador/{id}/download-backup/{filename}', name: 'app_download_backup', requirements: ['filename' => '.+'])]
    public function downloadBackup(
        Empresa $empresa,
        string $filename,
        SftpService $sftpService
    ): StreamedResponse {
        $filesystem = $sftpService->connect(
            $empresa->getHost(),
            $empresa->getPuerto(),
            $empresa->getUsuario(),
            $empresa->getPassword(),
            $empresa->getRutaRemota()
        );

        $stream = $filesystem->readStream($filename);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
        ]);
    }

    #[Route('/explorador/{id}/download/{filename}', name: 'app_download_pdf', requirements: ['filename' => '.+'])]
    public function download(
        Empresa $empresa,
        string $filename,
        SftpService $sftpService
    ): StreamedResponse {
        $filesystem = $sftpService->connect(
            $empresa->getHost(),
            $empresa->getPuerto(),
            $empresa->getUsuario(),
            $empresa->getPassword(),
            $empresa->getRutaRemota()
        );

        $stream = $filesystem->readStream($filename);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
        ]);
    }
}