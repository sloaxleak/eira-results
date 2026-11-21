---
name: "EIRA Resultados — Sistema de Diseño"
description: "Sistema de diseño clínico, cálido y accesible para la plataforma de gestión y entrega segura de resultados de tamizaje de Papanicolaou de EIRA en México."
---

## Overview

EIRA Resultados traduce la identidad cálida, humana y cercana de EIRA A.C. en una plataforma SaaS clínica de alta precisión para la gestión documental de estudios de Papanicolaou. El diseño combina superficies luminosas en tonos crema rosado suave (`#FFF8F8`) y tarjetas blancas limpias (`#FFFFFF`) con acentos en rosa coral institucional y tipografía redondeada **M PLUS Rounded 1c**, logrando un equilibrio entre confianza médica, orden operativo y trato digno para las pacientes.

El sistema está pensado para dos contextos complementarios: flujos operativos de escritorio para recepción administrativa y autorización clínica, y un portal móvil de consulta directa para pacientes. En todo momento se prioriza la legibilidad, el contraste accesible (WCAG AA), la distinción visual inequívoca entre estados documentales y la privacidad clínica (nunca se muestran diagnósticos médicos en tableros, notificaciones ni vistas previas).

## Colors

La paleta se deriva directamente del sitio web oficial de EIRA (`#E7959B`, `#FFF8F8`, `#211A1B`) y de sus logotipos institucionales (`#FBB6C2`), ajustando los tonos interactivos primarios (`#963341` y `#B54B58`) para garantizar contraste WCAG AA (>= 4.5:1) con texto blanco, mientras se conservan los tonos rosa suaves para acentos de marca y contenedores cálidos.

- **Primary (`#963341`) y Primary Container (`#B54B58`):** Acciones principales, botones de confirmación, estados activos de navegación y elementos de enfoque.
- **Brand Coral (`#E7959B`) y Brand Blush (`#FBB6C2`):** Acentos visuales de identidad EIRA, realces decorativos, bordes suaves y contenedores de marca (emparejados con texto oscuro cuando llevan contenido).
- **Superficies (`#FFF8F8` a `#FFFFFF`):** Fondo cálido rosado muy tenue (`surface`) con tarjetas elevadas en blanco puro (`surface-container-lowest`) y contenedores secundarios en rosa empolvado (`surface-container-low`, `surface-container`).
- **Estados semánticos:** Verde clínico (`success`), ámbar de revisión (`warning`), azul informativo (`info`) y rojo de rechazo o incidencia (`error`), siempre acompañados de etiqueta textual e ícono.

```yaml
colors:
  primary: "#963341"
  on-primary: "#FFFFFF"
  primary-container: "#B54B58"
  on-primary-container: "#FFEEEE"
  secondary: "#7A5559"
  on-secondary: "#FFFFFF"
  secondary-container: "#FFCED2"
  on-secondary-container: "#5F3E41"
  tertiary: "#405A65"
  on-tertiary: "#FFFFFF"
  tertiary-container: "#58727E"
  on-tertiary-container: "#E2F5FF"
  brand-coral: "#E7959B"
  brand-blush: "#FBB6C2"
  brand-pink-container: "#FFDADB"
  on-brand-pink-container: "#40000E"
  background: "#FFF8F8"
  on-background: "#211A1B"
  surface: "#FFF8F8"
  surface-dim: "#E4D7D9"
  surface-bright: "#FFFFFF"
  surface-container-lowest: "#FFFFFF"
  surface-container-low: "#FFF0F2"
  surface-container: "#F9EAED"
  surface-container-high: "#F3E5E7"
  surface-container-highest: "#EDDFE1"
  on-surface: "#211A1B"
  surface-variant: "#F9DCDC"
  on-surface-variant: "#564243"
  outline: "#897172"
  outline-variant: "#DCC0C1"
  inverse-surface: "#362E30"
  inverse-on-surface: "#FCEDEF"
  error: "#BA1A1A"
  on-error: "#FFFFFF"
  error-container: "#FFDAD5"
  on-error-container: "#930009"
  success: "#006C45"
  on-success: "#FFFFFF"
  success-container: "#D6F7E5"
  on-success-container: "#003822"
  warning: "#8E4E00"
  on-warning: "#FFFFFF"
  warning-container: "#FFDCC1"
  on-warning-container: "#4C2700"
  info: "#006494"
  on-info: "#FFFFFF"
  info-container: "#CBE6FF"
  on-info-container: "#00344F"
```

## Typography

Se utiliza **M PLUS Rounded 1c** de Google Fonts en toda la aplicación, honrando el carácter cercano, amable y claro de la marca EIRA. Sus terminaciones redondeadas y amplia altura de x favorecen la lectura en pantallas móviles y tableros clínicos. Los folios, códigos de acceso, fechas y métricas utilizan `tabular-nums` para alineación precisa.

```yaml
typography:
  display-lg:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 36px
    fontWeight: 700
    lineHeight: 44px
    letterSpacing: -0.5px
  display-lg-mobile:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 28px
    fontWeight: 700
    lineHeight: 34px
    letterSpacing: -0.25px
  headline-lg:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 28px
    fontWeight: 700
    lineHeight: 36px
    letterSpacing: -0.25px
  headline-md:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 22px
    fontWeight: 700
    lineHeight: 28px
    letterSpacing: 0px
  title-lg:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 18px
    fontWeight: 700
    lineHeight: 24px
    letterSpacing: 0px
  title-md:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 16px
    fontWeight: 700
    lineHeight: 22px
    letterSpacing: 0px
  body-lg:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 24px
    letterSpacing: 0px
  body-md:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 20px
    letterSpacing: 0px
  label-lg:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 14px
    fontWeight: 700
    lineHeight: 20px
    letterSpacing: 0.1px
  label-md:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 12px
    fontWeight: 700
    lineHeight: 16px
    letterSpacing: 0.2px
  label-sm:
    fontFamily: "M PLUS Rounded 1c"
    fontSize: 11px
    fontWeight: 500
    lineHeight: 14px
    letterSpacing: 0.3px
```

## Layout & Spacing

El sistema emplea una retícula fluida de 12 columnas en escritorio con barra lateral fija de `256px` (`w-64`) y área de contenido principal con margen generoso (`24px` a `32px`), y una columna única centrada en móvil (`390px`) con área táctil mínima de `44px` en todos los botones y controles interactivos. La escala de espaciado parte de una unidad base de `4px` con múltiplos de `8px`.

```yaml
spacing:
  gutter-xs: 4px
  gutter-sm: 8px
  gutter-md: 16px
  gutter-lg: 24px
  gutter-xl: 32px
  section: 40px
  page-margin: 24px
```

## Elevation & Depth

La jerarquía visual combina **capas tonales** (`surface` `#FFF8F8` → `surface-container-low` `#FFF0F2` → `surface-container-lowest` `#FFFFFF`) con **bordes sutiles de bajo contraste** (`outline-variant` `#DCC0C1`) y sombras ambientales muy suaves y cálidas (`shadow-sm` / `shadow-md`) inspiradas en las tarjetas del sitio web oficial de EIRA. Se evita el uso de sombras pesadas o desenfoques tipo *glassmorphism* sobre datos clínicos para mantener la máxima nitidez.

## Shapes

Las formas suaves y redondeadas reflejan la calidez de la identidad EIRA: botones principales en formato cápsula (`full`), tarjetas de contenido y visores con esquinas amplias (`lg` 16px y `xl` 24px), y campos de formulario o insignias de estado con redondeo equilibrado (`md` 12px).

```yaml
rounded:
  sm: 6px
  DEFAULT: 12px
  md: 12px
  lg: 16px
  xl: 24px
  full: 9999px
```

## Motion & Micro-interaction

Las transiciones son ágiles, serenas y discretas: cambios de estado en botones, pestañas y filas responden en `120ms`, mientras que la apertura de paneles o visores utiliza `220ms` con desaceleración suave. Se respeta `prefers-reduced-motion` y no se animan propiedades de geometría.

```yaml
motion:
  state-change:
    duration: 120ms
    delay: 0ms
    timingFunction: ease-out
  surface-enter:
    duration: 220ms
    delay: 0ms
    timingFunction: cubic-bezier(0.2, 0, 0, 1)
  surface-exit:
    duration: 150ms
    delay: 0ms
    timingFunction: cubic-bezier(0.3, 0, 1, 1)
```

## Voice & Content Strategy

- **Idioma y tono:** Español de México, respetuoso, claro, sereno y empático. Sin tecnicismos innecesarios para pacientes y con precisión operativa para el equipo clínico.
- **Privacidad clínica estricta:** Nunca se muestran diagnósticos, interpretaciones médicas ni hallazgos citológicos en tableros, listados, notificaciones SMS/WhatsApp ni vistas previas. Solo se muestran estados de proceso documental y disponibilidad neutral.
- **Distinción de estados:** Se separan visualmente cuatro dimensiones: Estado del documento (`Recibido`, `En revisión admin.`, `Listo para firma`), Autorización clínica (`Pendiente`, `Autorizado`, `Rechazado`), Notificación (`Sin enviar`, `Enviada`, `Entregada`, `Error de envío`) y Consulta del paciente (`Sin consultar`, `Consultado`, `Descargado`).
- **Datos de ejemplo:** Todos los registros ficticios se identifican claramente con la etiqueta `DEMO` (ej. `EIRA-DEMO-2026-0418`).
- **Etiquetas de acción:** Verbos orientados al resultado (`Autorizar publicación`, `Solicitar corrección`, `Consultar resultado`, `Registrar recepción`).

## Components

Component implementations live as individual HTML sticker sheet files in
`components/`. Each file contains the canonical, styled HTML components with
`<!-- COMPONENT: name -->` comment markers. Read each file before use.

- **Badge** → `@components/badge.html`: `badge-numeric`, `badge-dot`
- **Button** → `@components/button.html`: `button-primary`, `button-icon`, `button-with-icon`
- **Card** → `@components/card.html`: `card-elevated`, `card-action`, `card-interactive`, `card-interactive-2`
- **Input Field** → `@components/input-field.html`: `input-field-text`, `input-field-icon`
- **Table** → `@components/table.html`: `table-standard`, `table-interactive`, `table-selectable`
