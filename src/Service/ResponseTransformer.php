<?php

declare(strict_types=1);

namespace App\Service;

class ResponseTransformer
{
    private NarrativeGenerator $narrative;
    private CardBuilder $cards;

    private const SEVERITY_ORDER = [
        'critical' => 0,
        'high'     => 1,
        'medium'   => 2,
        'low'      => 3,
        'info'     => 4,
    ];

    public function __construct()
    {
        $this->narrative = new NarrativeGenerator();
        $this->cards = new CardBuilder();
    }

    public function transform(array $melchiorOutput): array
    {
        $patientId = $melchiorOutput['patient_id'];
        $evaluatedAt = $melchiorOutput['evaluated_at'] ?? date('c');
        $triggeredRules = $melchiorOutput['triggered_rules'] ?? [];
        $alerts = $melchiorOutput['alerts'] ?? [];
        $recommendations = $melchiorOutput['recommendations'] ?? [];
        $summary = $melchiorOutput['summary'] ?? [];

        // 1. Severidade geral
        $overallSeverity = $this->resolveOverallSeverity($summary, $alerts);

        // 2. Resumo narrativo
        $narrative = $this->narrative->generate(
            $patientId,
            $triggeredRules,
            $overallSeverity,
            $summary
        );

        // 3. Alertas categorizados e ordenados
        $categorizedAlerts = $this->categorizeAlerts($alerts);

        // 4. Recomendações priorizadas
        $prioritizedRecommendations = $this->prioritizeRecommendations(
            $recommendations,
            $triggeredRules
        );

        // 5. Ações imediatas (críticas + altas)
        $immediateActions = $this->extractImmediateActions($triggeredRules);

        // 6. Cards para a Interface
        $uiCards = $this->cards->build(
            $patientId,
            $evaluatedAt,
            $overallSeverity,
            $categorizedAlerts,
            $prioritizedRecommendations,
            $summary
        );

        // 7. Estatísticas rápidas para dashboard
        $dashboardStats = $this->buildDashboardStats($summary, $alerts, $overallSeverity);

        // 8. Response completa
        return [
            'service'       => 'resposta',
            'version'       => '1.0.0',
            'generated_at'  => date('c'),
            'patient_id'    => $patientId,
            'evaluated_at'  => $evaluatedAt,

            // --- Para renderização direta ---
            'severity'      => $overallSeverity,
            'narrative'     => $narrative,
            'dashboard'     => $dashboardStats,

            // --- Alertas (ordenados por severidade) ---
            'alerts'        => $categorizedAlerts,

            // --- Recomendações (priorizadas) ---
            'recommendations' => $prioritizedRecommendations,

            // --- Ações imediatas ---
            'immediate_actions' => $immediateActions,

            // --- Cards prontos para UI ---
            'ui_cards'      => $uiCards,
        ];
    }

    private function resolveOverallSeverity(array $summary, array $alerts): string
    {
        if (!empty($summary['highest_severity'])) {
            return $summary['highest_severity'];
        }

        $highest = 'info';
        foreach ($alerts as $alert) {
            $sev = $alert['severity'] ?? 'info';
            if ((self::SEVERITY_ORDER[$sev] ?? 99) < (self::SEVERITY_ORDER[$highest] ?? 99)) {
                $highest = $sev;
            }
        }

        return $highest;
    }

    private function categorizeAlerts(array $alerts): array
    {
        $categorized = [
            'critical' => [],
            'high'     => [],
            'medium'   => [],
            'low'      => [],
            'info'     => [],
        ];

        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'info';
            $category = $this->mapRuleToCategory($alert['rule'] ?? '');

            $categorized[$severity][] = [
                'message'  => $alert['message'],
                'rule'     => $alert['rule'] ?? 'desconhecida',
                'category' => $category,
                'icon'     => $this->severityToIcon($severity),
                'color'    => $this->severityToColor($severity),
            ];
        }

        // Remover categorias vazias
        return array_filter($categorized, fn($group) => !empty($group));
    }

    private function prioritizeRecommendations(array $recommendations, array $rules): array
    {
        // Mapear prioridade das regras
        $rulePriority = [];
        foreach ($rules as $rule) {
            $rulePriority[$rule['name']] = $rule['priority'] ?? 99;
        }

        $result = [];
        foreach ($recommendations as $rec) {
            $ruleName = $rec['rule'] ?? '';
            $result[] = [
                'message'    => $rec['message'],
                'rule'       => $ruleName,
                'priority'   => $rulePriority[$ruleName] ?? 99,
                'category'   => $this->mapRuleToCategory($ruleName),
                'timeframe'  => $this->extractTimeframe($rec['message']),
            ];
        }

        // Ordenar por prioridade
        usort($result, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $result;
    }

    private function extractImmediateActions(array $rules): array
    {
        $actions = [];

        foreach ($rules as $rule) {
            $priority = $rule['priority'] ?? 99;
            if ($priority <= 2) {
                foreach ($rule['actions'] as $action) {
                    if ($action['type'] === 'recommendation') {
                        $actions[] = [
                            'rule'     => $rule['name'],
                            'action'   => $action['message'],
                            'priority' => $priority,
                            'urgency'  => $priority === 1 ? 'agora' : 'em_breve',
                        ];
                    }
                }
            }
        }

        usort($actions, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $actions;
    }

    private function buildDashboardStats(
        array $summary,
        array $alerts,
        string $overallSeverity
    ): array {
        $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        foreach ($alerts as $alert) {
            $sev = $alert['severity'] ?? 'info';
            $bySeverity[$sev] = ($bySeverity[$sev] ?? 0) + 1;
        }

        return [
            'total_rules_evaluated'  => $summary['total_rules_evaluated'] ?? 0,
            'total_rules_triggered'  => $summary['total_rules_triggered'] ?? 0,
            'trigger_rate'           => $this->calcTriggerRate($summary),
            'alerts_by_severity'     => $bySeverity,
            'overall_severity'       => $overallSeverity,
            'overall_severity_color' => $this->severityToColor($overallSeverity),
            'overall_severity_label' => $this->severityToLabel($overallSeverity),
            'has_critical'           => $overallSeverity === 'critical',
            'has_immediate_actions'  => ($bySeverity['critical'] ?? 0) > 0 || ($bySeverity['high'] ?? 0) > 0,
        ];
    }

    private function calcTriggerRate(array $summary): string
    {
        $evaluated = $summary['total_rules_evaluated'] ?? 0;
        $triggered = $summary['total_rules_triggered'] ?? 0;

        if ($evaluated === 0) return '0%';
        return round(($triggered / $evaluated) * 100, 1) . '%';
    }

    private function mapRuleToCategory(string $ruleName): string
    {
        $map = [
            'emergencia_hipertensiva'            => 'cardiovascular',
            'hipertensao_estagio2'               => 'cardiovascular',
            'diabetico_hipertenso_nao_controlado' => 'metabólico',
            'hiperglicemia_moderada'             => 'metabólico',
            'suspeita_tuberculose'               => 'infectologia',
            'multimorbidade'                     => 'geral',
        ];

        return $map[$ruleName] ?? 'geral';
    }

    private function severityToIcon(string $severity): string
    {
        return match ($severity) {
            'critical' => 'alert-triangle',
            'high'     => 'alert-circle',
            'medium'   => 'info',
            'low'      => 'check-circle',
            default    => 'info',
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

    private function severityToLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Crítico',
            'high'     => 'Alto',
            'medium'   => 'Moderado',
            'low'      => 'Baixo',
            default    => 'Informacional',
        };
    }

    private function extractTimeframe(string $message): ?string
    {
        // Extrair prazo da mensagem (ex: "Reavaliar em 15 minutos")
        if (preg_match('/(?:reavaliar|repetir|retorno)\s+em\s+(\d+\s+\w+)/i', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }
}