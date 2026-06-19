<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResponseTransformer;

class ResponseController
{
    public function evaluate(): void
    {
        $rawInput = file_get_contents('php://input');

        if (empty($rawInput)) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Corpo da requisição vazio. Envie o JSON do Melchior.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => 'JSON inválido: ' . json_last_error_msg(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        // Validação mínima
        if (!isset($data['patient_id']) || !isset($data['triggered_rules'])) {
            http_response_code(422);
            echo json_encode([
                'error' => 'JSON deve conter pelo menos "patient_id" e "triggered_rules".',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        $transformer = new ResponseTransformer();
        $response = $transformer->transform($data);

        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}