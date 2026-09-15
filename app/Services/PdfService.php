<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/**
 * Thin wrapper around Dompdf so every PDF in the app (receipts, admission
 * letters, and anything added later) renders the same way. Requires:
 *   composer require dompdf/dompdf
 */
class PdfService
{
    public function render(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Reads the logo as a base64 data URI so it renders correctly inside a
     * PDF regardless of the server's file structure (Dompdf can't reliably
     * fetch relative/local image paths the way a browser can).
     */
    public function logoDataUri(): ?string
    {
        $path = public_path('images/logo-main.png');

        if (! file_exists($path)) {
            return null;
        }

        return 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
    }
}
