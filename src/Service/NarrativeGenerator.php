<?php

declare(strict_types=1);

namespace App\Service;

class NarrativeGenerator
{
    /**
     * Gera um resumo narrativo legível para o médico.
     */
    public function generate(
        string $patientId,
        array  $triggeredRules,
        string $overallSeverity,
        array  $summary
    ): array {
        $ruleNames = array_map(fn($r) => $this->humanizeRuleName($r['name']), $triggeredRules);
        $ruleCount = count($ruleNames);
        $evaluated = $summary['total_rules_evaluated'] ?? 0;

        // Montar frase das condições
        $conditionsText = $this->buildConditionsList($triggeredRules);

        // Texto narrativo principal
        $narrative = "Paciente {$patientId} ";
        $narrative .= $this->severityIntro($overallSeverity);
        $narrative .= " Apresenta {$conditionsText}. ";
        $evaluatedCount = $summary['total_rules_triggered'] ?? $evaluated ?? $ruleCount;
        $narrative .= "Foram acionadas {$ruleCount} de {$evaluatedCount} regras avaliadas, ";
        $narrative .= "com severidade máxima " . strtoupper($this->severityLabel($overallSeverity)) . ".";

        // Ação principal recomendada
        $primaryAction = $this->getPrimaryAction($triggeredRules);

        return [
            'headline'     => $this->buildHeadline($patientId, $overallSeverity, $ruleCount),
            'body'         => $narrative,
            'primary_action' => $primaryAction,
            'conditions'   => $ruleNames,
            'severity_text' => $this->severityDescription($overallSeverity),
        ];
    }

    private function buildHeadline(string $patientId, string $severity, int $ruleCount): string
    {
        $label = match ($severity) {
            'critical' => 'ATENÇÃO: Quadro Crítico',
            'high'     => 'Alerta: Situação de Alto Risco',
            'medium'   => 'Atenção: Condições Requerem Avaliação',
            default    => 'Informações do Paciente',
        };

        return "{$label} — {$ruleCount} regra(s) acionada(s)";
    }

    private function severityIntro(string $severity): string
    {
        return match ($severity) {
            'critical' => 'encontra-se em estado crítico que demanda intervenção imediata.',
            'high'     => 'apresenta condições de alto risco que requerem atenção prioritária.',
            'medium'   => 'apresenta condições clínicas que requerem acompanhamento.',
            default    => 'apresenta achados que merecem atenção.',
        };
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Crítica',
            'high'     => 'Alta',
            'medium'   => 'Moderada',
            'low'      => 'Baixa',
            default    => 'Informacional',
        };
    }

    private function severityDescription(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Intervenção imediata necessária. Risco de desfecho adverso se não tratado.',
            'high'     => 'Acompanhamento prioritário. Conduta deve ser definida em breve.',
            'medium'   => 'Avaliação clínica recomendada. Acompanhamento ambulatorial.',
            default    => 'Monitoramento de rotina.',
        };
    }

    private function buildConditionsList(array $rules): array|string
    {
        $names = array_map(fn($r) => $this->humanizeRuleName($r['name']), $rules);

        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);
        return implode(', ', $names) . ' e ' . $last;
    }

    private function humanizeRuleName(string $name): string
    {
        $map = [
            'emergencia_hipertensiva'             => 'emergência hipertensiva (PA crítica)',
            'hipertensao_estagio2'                => 'hipertensão estágio 2',
            'diabetico_hipertenso_nao_controlado' => 'diabetes descontrolado com hipertensão',
            'suspeita_tuberculose'                => 'suspeita de tuberculose',
            'multimorbidade'                      => 'multimorbidade',
            'hiperglicemia_moderada'              => 'hiperglicemia moderada',
        ];

        return $map[$name] ?? str_replace('_', ' ', $name);
    }

    private function getPrimaryAction(array $rules): ?array
    {
        // A regra de prioridade 1 define a ação principal
        foreach ($rules as $rule) {
            if (($rule['priority'] ?? 99) === 1) {
                foreach ($rule['actions'] as $action) {
                    if ($action['type'] === 'recommendation') {
                        return [
                            'description' => $action['message'],
                            'source_rule' => $rule['name'],
                        ];
                    }
                }
            }
        }

        // Fallback: primeira recommendation
        foreach ($rules as $rule) {
            foreach ($rule['actions'] as $action) {
                if ($action['type'] === 'recommendation') {
                    return [
                        'description' => $action['message'],
                        'source_rule' => $rule['name'],
                    ];
                }
            }
        }

        return null;
    }
}