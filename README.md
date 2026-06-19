# balthasar
---

## Estrutura do projeto:

```text
balthasar/
├── docker-compose.yml
├── Dockerfile
├── public/
│   └── index.php
├── src/
│   ├── Controller/
│   │   └── ResponseController.php
│   ├── Service/
│   │   ├── ResponseTransformer.php
│   │   ├── NarrativeGenerator.php
│   │   └── CardBuilder.php
│   └── Model/
│       └── EvaluationResponse.php
└── composer.json
```

---
A ideia é que a Interface receba essa resposta e simplesmente itere sobre ui_cards para montar a tela. Ela não precisa saber o que é "emergência hipertensiva" ou qual severidade é pior — o container Resposta já fez essa tradução toda.

---
## Como rodar

```bash
# Na pasta resposta/
docker-compose up -d --build

# Verificar que está rodando
curl http://localhost:8082/health
```

## Teste
```bash
curl -X POST http://localhost:8082/api/evaluate \
  -H "Content-Type: application/json" \
  -d @melchior-output.json | python3 -m json.tool
```

## O que a resposta devolve

A estrutura retornada pelo `/api/evaluate` fica assim:

```json
{
    "service": "resposta",
    "version": "1.0.0",
    "generated_at": "2026-06-17T11:33:00+00:00",
    "patient_id": "pac-001",
    "evaluated_at": "2026-06-17T11:32:44+00:00",

    // Severidade geral (para a UI saber a cor do banner)
    "severity": "critical",

    // Resumo narrativo pronto para exibição
    "narrative": {
        "headline": "ATENÇÃO: Quadro Crítico — 6 regra(s) acionada(s)",
        "body": "Paciente pac-001 encontra-se em estado crítico...",
        "primary_action": {
            "description": "Iniciar protocolo de crise hipertensiva...",
            "source_rule": "emergencia_hipertensiva"
        },
        "conditions": ["emergência hipertensiva", "hipertensão estágio 2", ...],
        "severity_text": "Intervenção imediata necessária..."
    },

    // Métricas para dashboard
    "dashboard": {
        "total_rules_evaluated": 10,
        "total_rules_triggered": 6,
        "trigger_rate": "60.0%",
        "alerts_by_severity": { "critical": 1, "high": 2, "medium": 3 },
        "overall_severity_color": "#dc2626",
        "overall_severity_label": "Crítico",
        "has_critical": true,
        "has_immediate_actions": true
    },

    // Alertas categorizados por severidade
    "alerts": {
        "critical": [{ "message": "...", "rule": "...", "category": "cardiovascular", "icon": "alert-triangle", "color": "#dc2626" }],
        "high": [...],
        "medium": [...]
    },

    // Recomendações priorizadas
    "recommendations": [
        { "message": "...", "priority": 1, "category": "cardiovascular", "timeframe": "15 minutos" },
        ...
    ],

    // Ações imediatas (prioridade 1 e 2)
    "immediate_actions": [
        { "action": "...", "priority": 1, "urgency": "agora" },
        { "action": "...", "priority": 2, "urgency": "em_breve" }
    ],

    // Cards prontos para renderizar
    "ui_cards": [
        { "type": "summary", "title": "Paciente pac-001", "metrics": [...] },
        { "type": "primary_alert", "title": "Alerta Principal", "body": "...", "style": {...} },
        { "type": "immediate_actions", "items": [...] },
        { "type": "recommendations", "items": [...] }
    ]
}
```