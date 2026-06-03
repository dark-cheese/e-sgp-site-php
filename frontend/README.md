# e-SGP - Sistema de Gestão de Patrimônio Público

## Estrutura do Projeto

```
e-sgp-frontend/
├── index.html          (Login)
├── dashboard.html      (Dashboard)
├── secretarias.html    (Secretarias)
├── unidades.html       (Unidades)
├── locais.html         (Locais)
├── itens.html          (Itens patrimoniais)
├── novo-item.html      (Cadastro de item)
├── relatorios.html     (Relatórios)
├── etiquetas.html      (Etiquetas)
│
├── css/
│   ├── main.css
│   ├── login.css
│   ├── dashboard.css
│   ├── secretarias.css
│   ├── unidades.css
│   ├── locais.css
│   ├── itens.css
│   ├── novo-item.css
│   ├── relatorios.css
│   └── etiquetas.css
│
├── js/
│   ├── main.js
│   ├── auth.js
│   └── utils.js
│
└── fonts/              ← CONFIGURAR CONFORME INSTRUÇÕES ABAIXO
    ├── css/
    │   └── all.min.css
    └── webfonts/
        ├── fa-brands-400.woff2
        ├── fa-regular-400.woff2
        ├── fa-solid-900.woff2
        └── (demais arquivos .woff2 e .ttf)
```

---

## Como configurar os ícones (Font Awesome local)

### Passo 1 — Baixar o Font Awesome

Acesse: https://fontawesome.com/download

Clique em **"Free for Web"** e faça o download do arquivo ZIP.

### Passo 2 — Extrair os arquivos

Extraia o ZIP baixado. Dentro dele você vai encontrar:
- pasta `css/` → contém o arquivo `all.min.css`
- pasta `webfonts/` → contém os arquivos de fonte (.woff2, .ttf)

### Passo 3 — Copiar para o projeto

Dentro da pasta `fonts/` do projeto:

1. Copie o arquivo `all.min.css` para `fonts/css/all.min.css`
2. Copie a pasta `webfonts/` inteira para `fonts/webfonts/`

### Passo 4 — Verificar

Abra qualquer arquivo HTML no navegador. Os ícones devem aparecer normalmente.

---

## Credenciais de demonstração

- **E-mail:** admin@prefeitura.br
- **Senha:** admin123

---

## Tecnologias utilizadas

- HTML5
- CSS3
- JavaScript (puro)
- Font Awesome 6 (local)

---

Desenvolvido como Projeto Integrador — IFSudesteMG Campus São João del-Rei — 2026
