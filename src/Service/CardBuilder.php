<?php

declare(strict_types=1);

namespace App\Service;

class CardBuilder
{
    /**
     * Monta cards prontos para a Interface renderizar.
     * Cada card tem: tipo, título, corpo, cor, ícone e metadados.
     */
    public function build(
        string $patientId,
        string $evaluatedAt,
        string $overallSeverity,
        array  $categorizedAlerts,
        array  $recommendations,
        array  $summary
    ): array {
        $cards = [];

        // Card 1: Resumo do paciente
        $cards[] = $this->buildSummaryCard(
            $patientId,
            $evaluatedAt,
            $overallSeverity,
            $summary
        );

        // Card 2: Alerta principal (severidade mais alta)
        $topAlert = $this->getTopAlert($categorizedAlerts);
        if ($topAlert) {
            $cards[] = $this->buildAlertCard($topAlert);
        }

        // Cards 3+: Alertas adicionais por severidade
        foreach ($categorizedAlerts as $severity => $alerts) {
            if ($severity === array_key_first($categorizedAlerts)) {
                continue; // Já foi coberto pelo top alert
            }
            foreach ($alerts as $alert) {
                $cards[] = [
                    'type'      => 'alert',
                    'severity'  => $severity,
                    'title'     => $this->severityToLabel($severity),
                    'body'      => $alert['message'],
                    'category'  => $alert['category'],
                    'icon'      => $alert['icon'],
                    'color'     => $alert['color'],
                    'rule'      => $alert['rule'],
                ];
            }
        }

        // Card de ações imediatas
        $immediateRecs = array_filter(
            $recommendations,
            fn($r) => $r['priority'] <= 2
        );
        if (!empty($immediateRecs)) {
            $cards[] = $this->buildActionsCard($immediateRecs);
        }

        // Card de recomendações restantes
        $otherRecs = array_filter(
            $recommendations,
            fn($r) => $r['priority'] > 2
        );
        if (!empty($otherRecs)) {
            $cards[] = $this->buildRecommendationsCard($otherRecs);
        }

        return $cards;
    }

    private function buildSummaryCard(
        string $patientId,
        string $evaluatedAt,
        string $overallSeverity,
        array  $summary
    ): array {
        $totalEvaluated = $summary['total_rules_evaluated'] ?? 0;
        $totalTriggered = $summary['total_rules_triggered'] ?? 0;

        return [
            'type'     => 'summary',
            'title'    => "Paciente {$patientId}",
            'subtitle' => "Avaliado em " . $this->formatDate($evaluatedAt),
            'severity' => $overallSeverity,
            'color'    => $this->severityToColor($overallSeverity),
            'icon'     => 'clipboard-pulse',
            'metrics'  => [
                [
                    'label'  => 'Regras Avaliadas',
                    'value'  => $totalEvaluated,
                    'color'  => '#6b7280',
                ],
                [
                    'label'  => 'Regras Acionadas',
                    'value'  => $totalTriggered,
                    'color'  => $this->severityToColor($overallSeverity),
                ],
                [
                    'label'  => 'Taxa de Acionamento',
                    'value'  => $totalEvaluated > 0
                        ? round(($totalTriggered / $totalEvaluated) * 100) . '%'
                        : '0%',
                    'color'  => $this->rateColor($totalTriggered, $totalEvaluated),
                ],
            ],
        ];
    }

    private function buildAlertCard(array $alert): array
    {
        return [
            'type'     => 'primary_alert',
            'title'    => 'Alerta Principal',
            'body'     => $alert['message'],
            'severity' => array_key_first(
                // Severidade já está no grupo pai
                []
            ),
            'category' => $alert['category'],
            'icon'     => $alert['icon'],
            'color'    => $alert['color'],
            'rule'     => $alert['rule'],
            'style'    => [
                'border_left' => true,
                'border_width' => '4px',
                'pulse'       => true,
            ],
        ];
    }

    private function getTopAlert(array $categorizedAlerts): ?array
    {
        foreach ($categorizedAlerts as $alerts) {
            if (!empty($alerts)) {
                return $alerts[0];
            }
        }
        return null;
    }

    private function buildActionsCard(array $recommendations): array
    {
        $items = array_map(fn($rec) => [
            'text'      => $rec['message'],
            'category'  => $rec['category'],
            'timeframe' => $rec['timeframe'],
            'priority'  => $rec['priority'],
        ], $recommendations);

        return [
            'type'     => 'immediate_actions',
            'title'    => 'Ações Imediatas',
            'subtitle' => count($items) . ' ação(ões) que requerem atenção agora',
            'icon'     => 'bolt',
            'color'    => '#dc2626',
            'items'    => $items,
            'style'    => [
                'variant' => 'urgent',
            ],
        ];
    }

    private function buildRecommendationsCard(array $recommendations): array
    {
        $items = array_map(fn($rec) => [
            'text'      => $rec['message'],
            'category'  => $rec['category'],
            'timeframe' => $rec['timeframe'],
            'priority'  => $rec['priority'],
        ], $recommendations);

        return [
            'type'     => 'recommendations',
            'title'    => 'Recomendações',
            'subtitle' => count($items) . ' recomendação(ões) para acompanhamento',
            'icon'     => 'list-check',
            'color'    => '#ca8a04',
            'items'    => $items,
            'style'    => [
                'variant' => 'standard',
            ],
        ];
    }

    private function severityToLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Crítico',
            'high'     => 'Alto Risco',
            'medium'   => 'Moderado',
            'low'      => 'Baixo',
            default    => 'Info',
        };
    }

    private function severityToColor(string $severity): string
    {
        return match ($severity) {
            'critical' => '#dc2626',
            'high'     => '#ea580c',
            'medium'   => '#ca8a04',
            'low'      => '#16a34a',
            default    => '#6b7280',
        };
    }

    private function rateColor(int $triggered, int $evaluated): string
    {
        if ($evaluated === 0) return '#6b7280';
        $rate = $triggered / $evaluated;

        if ($rate >= 0.6) return '#dc2626';
        if ($rate >= 0.3) return '#ca8a04';
        return '#16a34a';
    }

    private function formatDate(string $isoDate): string
    {
        try {
            $dt = new \DateTime($isoDate);
            return $dt->format('d/m/Y H:i');
        } catch (\Exception) {
            return $isoDate;
        }
    }
}