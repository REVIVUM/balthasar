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
