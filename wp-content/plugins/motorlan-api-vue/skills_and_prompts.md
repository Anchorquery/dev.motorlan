# Habilidades y Prompts Recomendados para Proyecto Vue.js/WordPress

Basado en el análisis de `@[.agent/skills/skills]`, aquí están las habilidades más efectivas para completar tu aplicación.

## 1. Arquitectura y Toma de Decisiones
**Skill:** `senior-architect`
**Propósito:** Decisiones arquitectónicas de alto nivel, organizando la estructura de Vue.js dentro de WordPress y asegurando la escalabilidad.
**Por qué:** Ayuda a evitar el "código espagueti" al integrar una SPA moderna (Vue) en un CMS tradicional (WordPress).

### Prompt
```markdown
@senior-architect
Necesito definir la arquitectura para una aplicación Vue.js embebida en un plugin de WordPress.
- **Objetivo**: Crear una estructura escalable para [Funcionalidad Específica, ej. Dashboard].
- **Restricciones**: Debe interactuar con la API REST de WordPress y manejar la verificación de nonces.
- **Salida**: Por favor revisa mi estructura actual en [ruta/a/archivos] y propón una jerarquía de componentes y una estrategia de gestión de estado (Pinia/Vuex) que se ajuste a este contexto.
```

## 2. Resolución de Errores (Debug)
**Skill:** `systematic-debugging`
**Propósito:** Resolver bugs complejos (como "fetch failed", "categorías no cargan") encontrando la causa raíz en lugar de adivinar.
**Por qué:** Es esencial para los errores "fetch failed" y 404 que estás experimentando actualmente.

### Prompt
```markdown
@systematic-debugging
Estoy enfrentando un error: "[Insertar Mensaje de Error]" al acceder a [Endpoint/Página].
- **Observación**: Ocurre cuando [Acción].
- **Contexto**: La API de WordPress devuelve [Código de Estado].
- **Solicitud**: Por favor guíame a través de las 4 fases de depuración sistemática para encontrar la causa raíz. No propongas una solución hasta que hayamos aislado el punto de fallo específico (ej. nonce, CORS, URL incorrecta).
```

## 3. Diseño Web y Finalización de Vistas
**Skill:** `ui-ux-pro-max`
**Propósito:** Diseñar vistas Vue.js premium y responsivas con un sistema de diseño consistente.
**Por qué:** Solicitaste "Diseño web para terminar vistas" y esta skill provee stacks específicos para Vue.

### Prompt
```markdown
@ui-ux-pro-max
Necesito terminar el diseño para la vista [Nombre de Vista, ej. Lista de Tareas] en Vue.js.
- **Tipo de Producto**: Dashboard/Panel de Admin dentro de WordPress.
- **Estilo**: Limpio, moderno, profesional (que combine con WP Admin pero se vea mejor).
- **Stack**: Vue.js + Tailwind CSS.
- **Solicitud**: 
    1. Genera un mini sistema de diseño (colores, tipografía, espaciado).
    2. Provee el código del componente Vue para [Componente, ej. Tabla de Datos] que siga las guías de accesibilidad y responsividad.
    3. Asegura que los estados de interacción (hover, activo, cargando) estén pulidos.
```

## 4. Implementación de API
**Skill:** `api-design-principles` (y `nodejs-backend-patterns` si controlas el lado del servidor, o skills genéricas de API)
**Propósito:** Implementar APIs faltantes de manera limpia y segura.
**Por qué:** Para asegurar que los endpoints que crees sigan un estándar RESTful y manejen los datos correctamente.

### Prompt
```markdown
@api-design-principles
Necesito implementar un nuevo endpoint de API para [Funcionalidad].
- **Contexto**: API REST de WordPress (PHP) sirviendo a un frontend Vue.js.
- **Requerimiento**: El endpoint debe aceptar [Datos de Entrada] y devolver [Datos de Salida].
- **Solicitud**: Diseña el contrato de la API (URL, Método, Cuerpo de Petición, Respuesta) siguiendo mejores prácticas. Luego, ayúdame a implementar la función callback en PHP asegurando validación y manejo de errores adecuados.
```

## 5. Calidad Frontend y Estándares
**Skill:** `frontend-dev-guidelines`
**Propósito:** Asegurar la calidad del código Vue.js, reusabilidad de componentes y rendimiento.
**Por qué:** Para mantener el código limpio mientras "terminas las vistas".

### Prompt
```markdown
@frontend-dev-guidelines
Revisa mi implementación de [Componente.vue].
- **Objetivo**: Asegurar que sigue las mejores prácticas de Vue.js (Composition API, tipado correcto de props, sin fugas de memoria).
- **Checklist**: Revisa cadenas harcodeadas, manejo de eventos adecuado e inmutabilidad del estado.
```

## 6. Flujo de Trabajo Full Stack (Combinado)
**Skills:** `senior-architect` + `api-design-principles` + `frontend-dev-guidelines`
**Propósito:** Analizar rutas/UI existentes e implementar nueva funcionalidad en una sola pasada coherente.

### Prompt
```markdown
@senior-architect @api-design-principles @frontend-dev-guidelines
Necesito implementar/modificar la funcionalidad [Nombre de la Funcionalidad].

Paso 1: Análisis
- Revisa el endpoint actual de la API en: [Ruta al archivo PHP]
- Revisa el componente Vue actual en: [Ruta al archivo Vue]
- Verifica discrepancias entre la respuesta de la API y las expectativas del Frontend.

Paso 2: Plan de Implementación
- Propón cambios a la API PHP para soportar [Nueva Funcionalidad].
- Propón actualizaciones al componente Vue para mostrar [Nuevos Datos/UI].

Paso 3: Ejecución
- Genera el código PHP para las actualizaciones del endpoint.
- Genera el código Vue.js para las actualizaciones de UI.
- Asegura que el manejo de errores (nonces, errores de red) sea robusto.
```
