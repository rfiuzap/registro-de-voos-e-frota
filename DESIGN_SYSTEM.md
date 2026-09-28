# Design System & UI/UX Guidelines
> **Referência de Design**: Sistema de Gestão Aeronáutica & Operações de Frota  
> **Estilo**: Modern Clean Aviation, Glassmorphism Sutil, Microinterações e Alta Densidade de Informação.

Este documento consolida todas as diretrizes visuais, tokens de estilo, componentes de interface e padrões de interação utilizados neste projeto para que você possa replicá-los com máxima fidelidade em novos projetos.

---

## 1. Filosofia de Design & Identidade Visual

O design deste sistema foi concebido para equilibrar **confiabilidade operacional**, **alta legibilidade de dados numéricos** e uma **estética contemporânea premium**:
- **Clareza Informacional**: Informações numéricas (altitudes, velocidades, consumos, coordenadas) possuem destaque com tipografia proporcional e badges de unidade (`kt`, `ft/min`, `NM`).
- **Glassmorphism Funcional**: Efeito de vidro jateado fosco (`backdrop-filter: blur(10px)`) em barras de navegação, cabeçalhos de tabelas e painéis flutuantes.
- **Hierarquia Visual Intuitiva**: Uso de cores semânticas suaves com pares de fundo e borda correspondentes (`--success`, `--success-bg`, `--success-border`).
- **Profundidade em Camadas**: Sombras em múltiplos estágios que conferem relevo sutil sem poluir o olhar.

---

## 2. Tipografia

### Fonte Principal
Utiliza a família **[Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans)**, que combina formas geométricas modernas com legibilidade técnica excelente.

```html
<!-- Importação no <head> -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
```

```css
body {
    font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
}
```

### Escala Tipográfica Recomendada
| Elemento | Tamanho | Peso | Line-Height | Uso |
| :--- | :--- | :--- | :--- | :--- |
| **Título Principal (H1)** | `clamp(22px, 3.5vw, 30px)` | 800 (Extra Bold) | 1.2 | Topo da página / Marca |
| **Título de Seção (H2)** | `18px - 22px` | 700 (Bold) | 1.3 | Cabeçalhos de cards e tabelas |
| **Subtítulos / Apoio** | `13px - 14px` | 500 (Medium) | 1.4 | Descrições e notas |
| **Corpo de Texto (Body)** | `14px` | 400 (Regular) | 1.5 | Parágrafos e formulários |
| **Rótulos (Labels)** | `12px - 13px` | 600 (Semi Bold) | 1.2 | Títulos de inputs |
| **Números de KPI / Destaque** | `24px - 32px` | 800 (Extra Bold) | 1.1 | Valores métricos principais |
| **Badges / Unidades** | `11px - 12px` | 600 ou 700 | 1.0 | Unidades de medida e status |

---

## 3. Tokens de Cores e Variáveis Globais (CSS Variables)

Copie este bloco `:root` para o início do seu `style.css`:

```css
:root {
    /* Neutros e Superfícies */
    --ink: #0f172a;               /* Cor principal de texto (Slate 900) */
    --ink-light: #334155;         /* Texto secundário (Slate 700) */
    --muted: #64748b;             /* Texto apagado / labels (Slate 500) */
    --line: #e2e8f0;              /* Bordas padrão (Slate 200) */
    --line-light: #f1f5f9;        /* Linhas divisórias suaves (Slate 100) */
    --paper: #ffffff;             /* Fundo de cards e modais */
    --wash: #f8fafc;              /* Fundo geral da página (Slate 50) */
    --surface-alt: #f1f5f9;       /* Fundo de campos e tabelas alternadas */

    /* Cores de Destaque / Aviação */
    --primary: #1d4ed8;           /* Azul Royal Aviação */
    --primary-hover: #1e40af;     /* Azul escuro no hover */
    --primary-light: #eff6ff;     /* Fundo suave de elementos ativos */
    --accent: #0284c7;            /* Azul celeste / destaque secundário */
    --teal: #0d9488;              /* Verde petróleo / eficiência */
    --teal-dark: #0f766e;
    --teal-light: #f0fdfa;

    /* Semânticos de Status */
    --success: #10b981;
    --success-bg: #ecfdf5;
    --success-border: #a7f3d0;

    --warning: #f59e0b;
    --warning-bg: #fffbeb;
    --warning-border: #fde68a;

    --danger: #ef4444;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;

    /* Cores Específicas de Mapa e Rota */
    --mapa-origem: #1d4ed8;       /* Azul (Origem) */
    --mapa-destino: #10b981;      /* Verde (Destino) */
    --mapa-toc: #ef4444;          /* Vermelho (Topo de Subida / Alertas) */
    --mapa-aeronave: #d946ef;     /* Magenta / Fuchsia (Ícone do Avião) */
    --mapa-rota-linha: #38bdf8;    /* Ciano / Azul vívido para traçado */

    /* Sombras em Camadas (Layered Drop Shadows) */
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -2px rgba(15, 23, 42, 0.04);
    --shadow-md: 0 10px 20px -3px rgba(15, 23, 42, 0.08), 0 4px 8px -4px rgba(15, 23, 42, 0.04);
    --shadow-lg: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.06);

    /* Raios de Arredondamento */
    --radius-sm: 8px;
    --radius: 12px;
    --radius-lg: 16px;
    --radius-pill: 9999px;
}
```

### Fundo da Página (Page Canvas)
O fundo não é um cinza sólido, mas uma iluminação radial suave que dá sofisticação:

```css
body {
    max-width: 1240px;
    margin: 0 auto;
    padding: 24px 20px 60px;
    color: var(--ink);
    background:
        radial-gradient(at 100% 0%, rgba(29, 78, 216, 0.05) 0px, transparent 45%),
        radial-gradient(at 0% 0%, rgba(13, 148, 136, 0.05) 0px, transparent 40%),
        #f8fafc;
    min-height: 100vh;
}
```

---

## 4. Componentes Chave da Interface

### 4.1 Cabeçalho & Marca (Header)
Apresenta o logotipo com efeito circular sutil e transição ao passar o mouse.

```html
<header class="topo">
    <div class="marca">
        <img src="logo.png" alt="Logotipo" class="logo-aplicacao">
        <div>
            <h1>Nome do Sistema</h1>
            <p>Subtítulo ou descrição curta operacional</p>
        </div>
    </div>
</header>
```

```css
.topo {
    margin-bottom: 20px;
}

.marca {
    display: flex;
    align-items: center;
    gap: 14px;
}

.logo-aplicacao {
    width: 52px;
    height: 52px;
    object-fit: contain;
    border-radius: 50%;
    box-shadow: 0 4px 14px rgba(29, 78, 216, 0.2);
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.logo-aplicacao:hover {
    transform: scale(1.06);
}

.marca h1 {
    margin: 0;
    color: var(--ink);
    font-size: clamp(22px, 3.5vw, 30px);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.2;
}

.marca p {
    margin: 2px 0 0;
    color: var(--muted);
    font-size: 13px;
    font-weight: 500;
}
```

---

### 4.2 Navegação em Abas (Capsule Tabs)
Barra estilo pílula translúcida com ícones vetoriais inline e texto com quebra elegante.

```html
<nav class="navegacao" aria-label="Navegação principal">
    <a href="index.php" class="ativo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        <span>Registro de<br>voos</span>
    </a>
    <a href="planejamento.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
        <span>Planejamento<br>de voo</span>
    </a>
</nav>
```

```css
.navegacao {
    display: flex;
    gap: 6px;
    margin: 18px 0 24px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    padding: 6px;
    border-radius: var(--radius);
    border: 1px solid var(--line);
    box-shadow: var(--shadow-sm);
    overflow-x: auto;
}

.navegacao a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--radius-sm);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.navegacao a svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    stroke: currentColor;
}

.navegacao a:hover {
    color: var(--ink);
    background: rgba(255, 255, 255, 0.9);
}

.navegacao a.ativo {
    color: var(--paper);
    background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);
}
```

---

### 4.3 Cards de KPI / Métricas (KPI Grid)
Organizam números chave em blocos limpos, com ícone e rótulo claro.

```html
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-rotulo">Horas Totais</div>
        <div class="kpi-valor">128,5 <span class="kpi-unidade">h</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-rotulo">Consumo Estimado</div>
        <div class="kpi-valor">450 <span class="kpi-unidade">gal</span></div>
    </div>
</div>
```

```css
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.kpi-card {
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.kpi-rotulo {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.kpi-valor {
    color: var(--ink);
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.kpi-unidade {
    font-size: 14px;
    font-weight: 600;
    color: var(--muted);
}
```

---

### 4.4 Campos de Formulário com Badge de Unidade
O badge da unidade (`kt`, `ft/min`, `NM`, `R$`) fica alinhado no próprio rótulo para facilitar a leitura.

```html
<label>
    <div class="label-com-unidade">
        <span>Velocidade de Cruzeiro</span>
        <span class="unidade-badge">kt</span>
    </div>
    <input type="number" placeholder="Ex: 150">
</label>
```

```css
.label-com-unidade {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
}

.label-com-unidade span:first-child {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-light);
}

.unidade-badge {
    font-size: 11px;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-light);
    border: 1px solid rgba(29, 78, 216, 0.15);
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

input[type="text"], input[type="number"], select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--line);
    border-radius: var(--radius-sm);
    background: var(--paper);
    color: var(--ink);
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

input:focus, select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
}
```

---

### 4.5 Seções Expansíveis (`<details>` / `<summary>`)
Substitui sanfonas JavaScript pesadas com elementos HTML nativos elegantes e sem dependências.

```html
<details class="parametros-avancados">
    <summary>
        <div class="summary-bloco-texto">
            <span class="summary-titulo-principal">Parâmetros Operacionais</span>
            <span class="summary-subtitulo">(calculados da aeronave)</span>
        </div>
    </summary>
    <div class="conteudo-expansivel">
        <!-- Campos ou configurações adicionais -->
    </div>
</details>
```

```css
details.parametros-avancados {
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 14px 18px;
    margin: 16px 0;
    transition: border-color 0.2s;
}

details.parametros-avancados[open] {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

details.parametros-avancados summary {
    cursor: pointer;
    user-select: none;
    list-style: none;
    display: flex;
    align-items: center;
}

details.parametros-avancados summary::-webkit-details-marker {
    display: none;
}

.summary-titulo-principal {
    font-weight: 700;
    font-size: 14px;
    color: var(--ink);
}

.summary-subtitulo {
    font-size: 12px;
    color: var(--muted);
    margin-left: 6px;
}
```

---

### 4.6 Tabelas de Dados Limpas (Data Tables)
Cabeçalhos elegantes, cantos arredondados, linhas de hover e suporte a badges de rota.

```css
.tabela-container {
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

th {
    background: var(--surface-alt);
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
}

td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--line-light);
    color: var(--ink-light);
}

tr:last-child td {
    border-bottom: none;
}

tr:hover td {
    background: rgba(248, 250, 252, 0.8);
}

/* Badge de Rota (ex: SBRB → SBMT) */
.badge-rota {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid rgba(29, 78, 216, 0.2);
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
}
```

---

### 4.7 Botões de Ação
Botões com gradiente sutil, estados de hover com elevação e variantes primária, secundária e de perigo.

```css
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);
    color: #ffffff;
    border: none;
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.2);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(29, 78, 216, 0.3);
}

.btn-secondary {
    background: var(--paper);
    color: var(--ink-light);
    border: 1px solid var(--line);
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}

.btn-secondary:hover {
    background: var(--surface-alt);
    border-color: var(--muted);
}
```

---

## 5. Padrão de Mapas & Gráficos Interativos (Leaflet / Chart.js)

### Cores Padronizadas de Rota:
- **Origem**: `#1d4ed8` (Azul)
- **Destino**: `#10b981` (Verde)
- **TOC (Topo de Subida)**: `#ef4444` (Vermelho)
- **TOD (Início de Descida)**: `#f59e0b` (Âmbar)
- **Linha Animada de Rota**: Traçado contínuo ciano (`#38bdf8`) com linha pontilhada branca sobreposta (`dashArray: '8, 16'`) e animação via CSS `@keyframes rotaAviaoDash`.
- **Ícone do Avião na Rota**: `#d946ef` (Fuchsia/Magenta com fundo branco e contorno translúcido).

---

## 6. Breakpoints de Responsividade (Media Queries)

Mantenha estes 3 breakpoints padrão:

```css
/* 1. Telas Médias / Tablets */
@media (max-width: 1024px) {
    body {
        padding: 16px 14px 40px;
    }
}

/* 2. Dispositivos Móveis (Landscape e Smartphones Maiores) */
@media (max-width: 768px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .navegacao {
        justify-content: flex-start;
    }
}

/* 3. Smartphones Pequenos */
@media (max-width: 480px) {
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    .marca h1 {
        font-size: 20px;
    }
    .logo-aplicacao {
        width: 42px;
        height: 42px;
    }
}
```

---

## 7. Como Importar Este Design em Outro Projeto

1. Adicione a fonte `Plus Jakarta Sans` no `<head>` do novo projeto.
2. Copie os tokens de `:root` e os estilos básicos para o `style.css` da nova aplicação.
3. Utilize as classes `.topo`, `.marca`, `.navegacao`, `.kpi-card`, `.tabela-container` e os estilos de formulário para estruturar as novas páginas.
4. Ajuste as cores temáticas em `:root` (se o projeto tiver uma identidade corporativa diferente, altere apenas `--primary` e os neutros manterão toda a harmonia do layout).
