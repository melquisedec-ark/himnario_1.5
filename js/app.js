/**
 * ========================================================================
 *  HIMNARIO DIGITAL v1.5 — JAVASCRIPT CENTRAL
 *  Framework: Bootstrap 5.3 (vía CDN)
 *  Módulos: ThemeManager, LiveSearch, EstrofaManager,
 *           VersionSelector, FullscreenManager, UI Utils
 * ========================================================================
 *
 * NOTA PARA EL AGENTE DEV:
 * - Cada módulo es independiente. Importa solo lo que necesites en cada página.
 * - Usa `Himnario.initPage('nombre-pagina')` al final del DOMContentLoaded
 *   para activar solo los módulos de esa página.
 * - Las páginas son: 'index', 'presentacion', 'admin-index', 'admin-crear',
 *   'admin-editar', 'login'
 *
 * ========================================================================
 */

'use strict';

// ========================================================================
// HIMNARIO — NAMESPACE PRINCIPAL (evita colisiones con otras librerías)
// ========================================================================
window.Himnario = (function() {

    // ====================================================================
    // 1. CONFIGURACIÓN GLOBAL
    // ====================================================================
    const CONFIG = {
        theme: {
            storageKey: 'himnario_theme',
            defaultTheme: 'light',
            attr: 'data-bs-theme',
        },
        search: {
            debounceMs: 300,
            minLength: 2,
        },
        versionSelector: {
            storageKey: 'himnario_pais',
        },
        fullscreen: {
            storageKey: 'himnario_fullscreen',
        },
    };

    // ====================================================================
    // 2. MÓDULO: GESTOR DE TEMA (ThemeManager)
    // Usado en: TODAS las páginas
    // ====================================================================

    /**
     * ThemeManager — Control de modo claro/oscuro
     *
     * USO EN HTML:
     *   <button onclick="Himnario.ThemeManager.toggle()">🌗 Tema</button>
     *
     * MÉTODOS EXPUESTOS:
     *   .init()       → Inicializa el tema desde localStorage
     *   .set(theme)   → 'light' | 'dark' — fuerza un tema
     *   .toggle()     → Cambia entre claro y oscuro
     *   .get()        → Devuelve el tema actual
     *   .isDark()     → Boolean: true si está en oscuro
     *   .isLight()    → Boolean: true si está en claro
     */
    const ThemeManager = {
        _current: 'light',

        /**
         * Inicializa el tema. Debe llamarse en DOMContentLoaded.
         * @param {string} [forcedTheme] - Opcional: fuerza un tema
         * @returns {void}
         */
        init: function(forcedTheme) {
            try {
                const saved = forcedTheme || localStorage.getItem(CONFIG.theme.storageKey) || CONFIG.theme.defaultTheme;
                this._current = saved;
                this._apply(saved);
            } catch (e) {
                console.warn('[ThemeManager] Error al leer tema:', e.message);
                this._current = 'light';
                this._apply('light');
            }
        },

        /**
         * Aplica el tema al documento.
         * @param {string} theme - 'light' | 'dark'
         * @private
         */
        _apply: function(theme) {
            if (!document || !document.documentElement) return;

            const html = document.documentElement;
            html.setAttribute(CONFIG.theme.attr, theme);

            // Forzar data-bs-theme también en <body> para compatibilidad
            document.body.setAttribute(CONFIG.theme.attr, theme);

            // Actualizar meta-theme-color (útil para PWA)
            const metaTheme = document.querySelector('meta[name="theme-color"]');
            if (metaTheme) {
                metaTheme.setAttribute('content', theme === 'dark' ? '#0f0f1a' : '#1a5276');
            }

            // Disparar evento personalizado para que otros scripts reaccionen
            document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: theme } }));
        },

        /**
         * Fuerza un tema específico.
         * @param {string} theme - 'light' | 'dark'
         * @returns {void}
         */
        set: function(theme) {
            if (!['light', 'dark'].includes(theme)) {
                theme = 'light';
            }
            this._current = theme;
            this._apply(theme);
            try {
                localStorage.setItem(CONFIG.theme.storageKey, theme);
            } catch (e) {
                console.warn('[ThemeManager] No se pudo guardar en localStorage:', e.message);
            }
        },

        /**
         * Alterna entre claro y oscuro.
         * @returns {string} El nuevo tema aplicado
         */
        toggle: function() {
            const next = this._current === 'dark' ? 'light' : 'dark';
            this.set(next);
            return next;
        },

        get: function() {
            return this._current;
        },

        isDark: function() {
            return this._current === 'dark';
        },

        isLight: function() {
            return this._current === 'light';
        },

        /**
         * Escucha cambios de tema desde Bootstrap.
         * @returns {void}
         */
        listenBootstrapChanges: function() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (
                        mutation.type === 'attributes' &&
                        mutation.attributeName === CONFIG.theme.attr
                    ) {
                        const newTheme = document.documentElement.getAttribute(CONFIG.theme.attr);
                        if (newTheme && newTheme !== this._current) {
                            this._current = newTheme;
                            this._apply(newTheme);
                            try {
                                localStorage.setItem(CONFIG.theme.storageKey, newTheme);
                            } catch (e) { /* ignora */ }
                        }
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: [CONFIG.theme.attr],
            });
        },
    };

    // ====================================================================
    // 3. MÓDULO: BÚSQUEDA EN VIVO (LiveSearch)
    // Usado en: index.php
    // ====================================================================

    /**
     * LiveSearch — Búsqueda en vivo con debounce.
     *
     * USO EN HTML:
     *   <input type="text" id="search-input" ...>
     *   <script>
     *     Himnario.LiveSearch.init('#search-input', '#results-container');
     *   </script>
     *
     * NOTA AL DEV: Si el backend usa formulario GET tradicional,
     *   .search() hará submit al formulario. Si prefieres AJAX,
     *   sobrescribe .search() con una llamada fetch().
     */
    const LiveSearch = {
        _timeout: null,
        _lastQuery: '',

        /**
         * Inicializa el buscador en vivo.
         * @param {string} inputSelector - Selector CSS del input
         * @param {string} [resultsSelector] - Selector del contenedor de resultados
         * @param {string} [formSelector] - Selector del formulario
         * @returns {void}
         */
        init: function(inputSelector, resultsSelector, formSelector) {
            const input = document.querySelector(inputSelector);
            if (!input) {
                console.warn('[LiveSearch] No se encontró el input:', inputSelector);
                return;
            }

            this._input = input;
            this._resultsEl = resultsSelector ? document.querySelector(resultsSelector) : null;
            this._form = formSelector ? document.querySelector(formSelector) : null;

            // Si no hay formulario, busca el formulario padre más cercano
            if (!this._form) {
                this._form = input.closest('form');
            }

            // Evento de input con debounce
            input.addEventListener('input', (e) => {
                const value = e.target.value.trim();
                this._onInput(value);
            });

            // También soporta tecla Enter (búsqueda inmediata)
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._cancelTimeout();
                    this.search(e.target.value.trim());
                }
            });

            // Evento de blur: si hay algo escrito, buscar
            input.addEventListener('blur', (e) => {
                const value = e.target.value.trim();
                if (value.length >= CONFIG.search.minLength) {
                    this._cancelTimeout();
                    this.search(value);
                }
            });

            console.log('[LiveSearch] Inicializado en:', inputSelector);
        },

        /**
         * Maneja el input con debounce.
         * @param {string} query
         * @private
         */
        _onInput: function(query) {
            this._cancelTimeout();

            // Si está vacío, buscar sin filtro
            if (query.length === 0) {
                this._timeout = setTimeout(() => this.search(''), 100);
                return;
            }

            // Si tiene al menos minLength caracteres, buscar
            if (query.length >= CONFIG.search.minLength) {
                this._timeout = setTimeout(() => this.search(query), CONFIG.search.debounceMs);
            }
        },

        /**
         * Cancela el timeout pendiente.
         * @private
         */
        _cancelTimeout: function() {
            if (this._timeout !== null) {
                clearTimeout(this._timeout);
                this._timeout = null;
            }
        },

        /**
         * Ejecuta la búsqueda. Por defecto, envía el formulario.
         * @param {string} query - Término de búsqueda
         * @returns {void}
         */
        search: function(query) {
            this._lastQuery = query;

            // Actualizar el input visual para feedback
            if (this._input) {
                this._input.value = query;
            }

            // Si hay un formulario, actualizar y enviar
            if (this._form) {
                const qInput = this._form.querySelector('input[name="q"]');
                if (qInput) {
                    qInput.value = query;
                }
                this._form.submit();
            } else {
                console.warn('[LiveSearch] No hay formulario para enviar.');
            }

            // Disparar evento de búsqueda
            document.dispatchEvent(new CustomEvent('searchExecuted', {
                detail: { query: query, timestamp: Date.now() },
            }));
        },

        cancel: function() {
            this._cancelTimeout();
        },

        getLastQuery: function() {
            return this._lastQuery;
        },
    };

    // ====================================================================
    // 4. MÓDULO: GESTIÓN DE ESTROFAS (EstrofaManager)
    // Usado en: admin/crear.php, admin/editar.php
    // ====================================================================

    /**
     * EstrofaManager — Gestión dinámica de bloques de estrofas.
     *
     * USO EN HTML:
     *   <div id="estrofas-container"></div>
     *   <button onclick="Himnario.EstrofaManager.add('verso')">+ Verso</button>
     *   <button onclick="Himnario.EstrofaManager.add('coro')">+ Coro</button>
     *
     * MÉTODOS EXPUESTOS:
     *   .init(containerSelector, data) → Inicializa con datos existentes
     *   .add(tipo, contenido, orden)    → Agrega un bloque
     *   .remove(id)                      → Elimina por ID del elemento
     *   .getAll()                        → Array de {tipo, contenido, orden}
     *   .clear()                          → Vacía todas las estrofas
     */
    const EstrofaManager = {
        _container: null,
        _count: 0,
        _data: [],

        /**
         * Inicializa el contenedor de estrofas.
         * @param {string} containerSelector - Selector CSS del contenedor
         * @param {Array} [initialData] - Array de {tipo, contenido} para precargar
         * @returns {void}
         */
        init: function(containerSelector, initialData) {
            this._container = document.querySelector(containerSelector);
            if (!this._container) {
                console.warn('[EstrofaManager] Contenedor no encontrado:', containerSelector);
                return;
            }

            this._container.innerHTML = '';
            this._count = 0;
            this._data = [];

            // Si hay datos iniciales, precargar
            if (initialData && Array.isArray(initialData)) {
                initialData.forEach((item, index) => {
                    this.add(item.tipo || 'estrofa', item.contenido || '', index + 1);
                });
            }

            console.log('[EstrofaManager] Inicializado en:', containerSelector);
        },

        /**
         * Agrega un nuevo bloque de estrofa al formulario.
         * @param {string} tipo - 'estrofa' | 'coro' | 'puente' | 'intro' | 'final'
         * @param {string} contenido - Texto de la estrofa
         * @param {number|null} [orden] - Número de orden (opcional)
         * @returns {HTMLElement} El elemento creado
         */
        add: function(tipo, contenido, orden) {
            if (!this._container) return null;

            this._count++;
            const num = orden || this._count;

            const div = document.createElement('div');
            div.className = 'estrofa-box';
            div.dataset.tipo = tipo;
            div.dataset.orden = num;
            div.id = 'estrofa-' + this._count;

            // Determinar etiqueta visual según tipo
            let etiquetaHTML = '';
            switch (tipo) {
                case 'coro':
                    etiquetaHTML = '<span class="badge bg-warning text-dark">CORO</span>';
                    break;
                case 'estrofa':
                    etiquetaHTML = '<span class="badge bg-primary">ESTROFA</span>';
                    break;
                case 'puente':
                    etiquetaHTML = '<span class="badge bg-info text-dark">PUENTE</span>';
                    break;
                case 'intro':
                    etiquetaHTML = '<span class="badge bg-secondary">INTRO</span>';
                    break;
                case 'final':
                    etiquetaHTML = '<span class="badge bg-danger">FINAL</span>';
                    break;
                default:
                    etiquetaHTML = '<span class="badge bg-primary">VERSO</span>';
            }

            div.innerHTML =
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                etiquetaHTML +
                '<div>' +
                '<span class="badge bg-dark me-1">#' + num + '</span>' +
                '<button type="button" class="btn-eliminar btn btn-sm btn-outline-danger" ' +
                'onclick="this.closest(\'.estrofa-box\').remove()">✕</button>' +
                '</div>' +
                '</div>' +
                '<input type="hidden" name="tipo[]" value="' + tipo + '">' +
                '<textarea name="contenido[]" class="form-control estrofa-textarea" rows="4" ' +
                'placeholder="Escribe la letra aquí..." required>' +
                (contenido || '') +
                '</textarea>';

            this._container.appendChild(div);
            this._data.push({ tipo: tipo, contenido: contenido || '', orden: num });

            // Focus automático en el textarea
            const textarea = div.querySelector('textarea');
            if (textarea) {
                setTimeout(() => textarea.focus(), 50);
            }

            return div;
        },

        /**
         * Elimina un bloque por su ID.
         * @param {string} id - ID del elemento a eliminar
         * @returns {boolean} true si se eliminó
         */
        remove: function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.remove();
                this._reindex();
                return true;
            }
            return false;
        },

        /**
         * Devuelve todos los datos de estrofas actuales.
         * @returns {Array<{tipo: string, contenido: string, orden: number}>}
         */
        getAll: function() {
            const blocks = this._container.querySelectorAll('.estrofa-box');
            const data = [];
            blocks.forEach((block, index) => {
                data.push({
                    tipo: block.querySelector('input[name="tipo[]"]')?.value || 'estrofa',
                    contenido: block.querySelector('textarea')?.value || '',
                    orden: index + 1,
                });
            });
            return data;
        },

        /**
         * Vacía todas las estrofas.
         * @returns {void}
         */
        clear: function() {
            if (this._container) {
                this._container.innerHTML = '';
                this._count = 0;
                this._data = [];
            }
        },

        /**
         * Reindexa las estrofas después de una eliminación.
         * @private
         */
        _reindex: function() {
            this._count = 0;
            const blocks = this._container.querySelectorAll('.estrofa-box');
            blocks.forEach((block, index) => {
                this._count = index + 1;
                block.querySelector('.badge.bg-dark')?.textContent = '#' + (index + 1);
            });
        },
    };

    // ====================================================================
    // 5. MÓDULO: SELECTOR DE VERSIÓN POR PAÍS (VersionSelector)
    // Usado en: presentacion.php
    // ====================================================================

    /**
     * VersionSelector — Permite al usuario elegir qué versión
     *   de himno mostrar según el país (versiones_pais).
     *
     * USO EN HTML:
     *   <select id="pais-selector" onchange="Himnario.VersionSelector.select(value)">
     *     <option value="MX">México</option>
     *     <option value="AR">Argentina</option>
     *   </select>
     *
     * MÉTODOS EXPUESTOS:
     *   .init(selector, options)       → Inicializa con lista de países
     *   .select(paisId)                 → Cambia la versión activa
     *   .getCurrent()                    → Devuelve el país actual
     *   .getVersionData(himnoId)        → Fetch de estrofas por país
     */
    const VersionSelector = {
        _current: null,
        _selector: null,
        _versionCache: {},

        /**
         * Inicializa el selector.
         * @param {string} selector - Selector CSS del <select> o contenedor
         * @param {Array} [paises] - Array de {id, nombre, codigo}
         * @param {string} [defaultPais] - País por defecto
         * @returns {void}
         */
        init: function(selector, paises, defaultPais) {
            this._selector = document.querySelector(selector);
            if (!this._selector) {
                console.warn('[VersionSelector] Selector no encontrado:', selector);
                return;
            }

            // Si no es un <select>, crear uno
            if (this._selector.tagName !== 'SELECT') {
                const select = document.createElement('select');
                select.className = 'form-select';
                select.id = 'version-pais-select';
                this._selector.appendChild(select);
                this._selector = select;
            }

            this._selector.innerHTML = '';

            // Opción por defecto
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = 'Seleccionar versión...';
            defaultOpt.disabled = true;
            defaultOpt.selected = true;
            this._selector.appendChild(defaultOpt);

            // Cargar países
            if (paises && Array.isArray(paises)) {
                paises.forEach(function(pais) {
                    var opt = document.createElement('option');
                    opt.value = pais.id || pais.codigo;
                    opt.textContent = pais.nombre || pais.codigo;
                    if (pais.id === defaultPais || pais.codigo === defaultPais) {
                        opt.selected = true;
                        this._current = pais.id || pais.codigo;
                    }
                    this._selector.appendChild(opt);
                }.bind(this));
            }

            // Guardar selección
            this._selector.addEventListener('change', function(e) {
                this.select(e.target.value);
            }.bind(this));

            console.log('[VersionSelector] Inicializado con', paises ? paises.length : 0, 'países');
        },

        /**
         * Cambia la versión activa.
         * @param {string|number} paisId - ID del país
         * @returns {void}
         */
        select: function(paisId) {
            this._current = paisId;
            try {
                localStorage.setItem(CONFIG.versionSelector.storageKey, paisId);
            } catch (e) { /* ignora */ }

            document.dispatchEvent(new CustomEvent('versionChanged', {
                detail: { paisId: paisId },
            }));
        },

        /**
         * @returns {string|null} País actual
         */
        getCurrent: function() {
            return this._current;
        },

        /**
         * Obtiene los datos de versión de un himno para un país.
         * Nota para DEV: Implementar fetch real cuando el backend esté listo.
         * @param {number} himnoId - ID del himno
         * @param {string} paisId - Código del país
         * @returns {Promise}
         */
        getVersionData: function(himnoId, paisId) {
            var cacheKey = himnoId + '-' + paisId;

            if (this._versionCache[cacheKey]) {
                return Promise.resolve(this._versionCache[cacheKey]);
            }

            // Fetch al backend para obtener versión del país
            var url = CONFIG.endpoints.himno + '?id=' + himnoId + '&pais=' + paisId;

            return fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    this._versionCache[cacheKey] = data;
                    return data;
                }.bind(this))
                .catch(function(error) {
                    console.error('[VersionSelector] Error al cargar versión:', error);
                    return null;
                });
        },

        /**
         * Limpia la caché de versiones.
         * @returns {void}
         */
        clearCache: function() {
            this._versionCache = {};
        },
    };

    // ====================================================================
    // 6. MÓDULO: PANTALLA COMPLETA (FullscreenManager)
    // Usado en: presentacion.php
    // ====================================================================

    /**
     * FullscreenManager — API de pantalla completa para el visor.
     *
     * USO EN HTML:
     *   <button onclick="Himnario.FullscreenManager.toggle()">
     *     ⛶ Pantalla Completa
     *   </button>
     *
     * MÉTODOS EXPUESTOS:
     *   .toggle()        → Alterna pantalla completa
     *   .enter()         → Entra en modo fullscreen
     *   .exit()          → Sale del modo fullscreen
     *   .isFullscreen()  → Boolean
     *   .onChange(cb)    → Callback cuando cambia estado
     */
    const FullscreenManager = {
        _element: null,
        _callback: null,

        /**
         * Inicializa el módulo.
         * @param {string|HTMLElement} [element] - Elemento a poner en fullscreen
         * @param {Function} [onChange] - Callback al cambiar estado
         * @returns {void}
         */
        init: function(element, onChange) {
            this._element = element
                ? (typeof element === 'string' ? document.querySelector(element) : element)
                : document.documentElement;

            if (typeof onChange === 'function') {
                this._callback = onChange;
            }

            // Escuchar cambios nativos de fullscreen
            document.addEventListener('fullscreenchange', function() {
                if (this._callback) {
                    this._callback(document.fullscreenElement !== null);
                }
            }.bind(this));
        },

        /**
         * Solicita entrar en pantalla completa.
         * @returns {Promise<boolean>}
         */
        enter: function() {
            var el = this._element || document.documentElement;

            if (el.requestFullscreen) {
                return el.requestFullscreen()
                    .then(function() { return true; })
                    .catch(function(err) {
                        console.warn('[Fullscreen] Error:', err.message);
                        return false;
                    });
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
                return Promise.resolve(true);
            } else if (el.msRequestFullscreen) {
                el.msRequestFullscreen();
                return Promise.resolve(true);
            } else {
                console.warn('[Fullscreen] API no soportada en este navegador');
                return Promise.resolve(false);
            }
        },

        /**
         * Sale de pantalla completa.
         * @returns {void}
         */
        exit: function() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        },

        /**
         * Alterna entre entrar y salir.
         * @returns {void}
         */
        toggle: function() {
            if (this.isFullscreen()) {
                this.exit();
            } else {
                this.enter();
            }
        },

        /**
         * @returns {boolean} true si está en fullscreen
         */
        isFullscreen: function() {
            return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
        },

        /**
         * Registra un callback para cambios de estado.
         * @param {Function} cb - Función(boolean: isFullscreen)
         * @returns {void}
         */
        onChange: function(cb) {
            this._callback = cb;
        },
    };

    // ====================================================================
    // 7. MÓDULO: UTILIDADES DE UI (UIUtils)
    // ====================================================================

    /**
     * UIUtils — Funciones útiles para el frontend.
     */
    const UIUtils = {
        /**
         * Muestra un toast de notificación temporal.
         * @param {string} message - Mensaje a mostrar
         * @param {'success'|'error'|'info'} [type] - Tipo de notificación
         * @param {number} [duration] - Duración en ms
         * @returns {void}
         */
        showToast: function(message, type, duration) {
            type = type || 'success';
            duration = duration || 3000;

            // Crear o reutilizar contenedor de toasts
            var container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText =
                    'position:fixed;bottom:20px;right:20px;z-index:9999;' +
                    'display:flex;flex-direction:column;gap:10px;';
                document.body.appendChild(container);
            }

            var toast = document.createElement('div');
            toast.className = 'notification-toast ' + type;
            toast.textContent = message;
            toast.style.cssText =
                'padding:12px 24px;background:var(--bg-card);' +
                'border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
                'border-left:4px solid ' +
                (type === 'error' ? 'var(--color-danger)' :
                 type === 'success' ? 'var(--color-success)' :
                 'var(--color-primary)') + ';' +
                'transform:translateX(0);opacity:1;' +
                'transition:all 0.3s ease;' +
                'font-family:var(--font-body);' +
                'font-size:14px;color:var(--text-primary);';

            container.appendChild(toast);

            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(50px)';
                setTimeout(function() { toast.remove(); }, 300);
            }, duration);
        },

        /**
         * Muestra un spinner de carga.
         * @param {string} [message] - Mensaje opcional
         * @returns {HTMLElement} Elemento del spinner
         */
        showLoading: function(message) {
            var div = document.createElement('div');
            div.className = 'loading-state';
            div.innerHTML =
                '<div class="spinner"></div>' +
                (message ? '<span style="margin-top:8px">' + message + '</span>' : '');
            return div;
        },

        /**
         * Debounce genérico para funciones.
         * @param {Function} fn - Función a debouncer
         * @param {number} [ms] - Milisegundos
         * @returns {Function}
         */
        debounce: function(fn, ms) {
            ms = ms || 300;
            var timeout;
            return function() {
                var args = arguments;
                var context = this;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    fn.apply(context, args);
                }, ms);
            };
        },

        /**
         * Formatea un timestamp para mostrar.
         * @param {string|Date} date - Fecha
         * @returns {string} Fecha formateada
         */
        formatDate: function(date) {
            var d = new Date(date);
            return d.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },

        /**
         * Escapa HTML para prevenir XSS.
         * @param {string} str - String a escapar
         * @returns {string}
         */
        escapeHtml: function(str) {
            if (!str) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Detecta si el dispositivo es táctil.
         * @returns {boolean}
         */
        isTouchDevice: function() {
            return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        },
    };

    // ====================================================================
    // 8. INICIALIZACIÓN POR PÁGINA (PageRouter)
    // ====================================================================

    /**
     * PageRouter — Inicializa los módulos según la página actual.
     *
     * LLAMAR EN CADA PÁGINA PHP:
     *   <script>
     *     document.addEventListener('DOMContentLoaded', function() {
     *       Himnario.PageRouter.init('index');   // para index.php
     *       // o
     *       Himnario.initPage('presentacion');  // atajo
     *     });
     *   </script>
     */
    const PageRouter = {
        /**
         * Inicializa la página actual.
         * @param {string} pageName - Nombre de la página
         * @returns {void}
         */
        init: function(pageName) {
            // Siempre inicializar ThemeManager
            ThemeManager.init();

            // Inicializar módulos según la página
            switch (pageName) {
                case 'index':
                    this._initIndex();
                    break;
                case 'presentacion':
                    this._initPresentacion();
                    break;
                case 'admin-index':
                    this._initAdminIndex();
                    break;
                case 'admin-crear':
                    this._initAdminCrear();
                    break;
                case 'admin-editar':
                    this._initAdminEditar();
                    break;
                case 'login':
                    this._initLogin();
                    break;
                default:
                    console.warn('[PageRouter] Página desconocida:', pageName);
                    break;
            }

            console.log('[PageRouter] Página inicializada:', pageName);
        },

        /**
         * Inicializa módulos para index.php.
         * @private
         */
        _initIndex: function() {
            // Búsqueda en vivo (opcional, si hay input de búsqueda)
            if (document.querySelector('input[name="q"]')) {
                LiveSearch.init('input[name="q"]', '#results-container');
            }

            // ThemeManager ya está inicializado arriba
            // Escuchar cambios de tema desde Bootstrap
            ThemeManager.listenBootstrapChanges();
        },

        /**
         * Inicializa módulos para presentacion.php.
         * @private
         */
        _initPresentacion: function() {
            // Fullscreen para el contenedor principal
            FullscreenManager.init('#slide-container', function(isFS) {
                if (isFS) {
                    document.body.classList.add('fullscreen-active');
                } else {
                    document.body.classList.remove('fullscreen-active');
                }
            });

            // Selector de versión por país
            // NOTA: El backend debe pasar los países como JSON
            var paisesData = document.getElementById('paises-data');
            if (paisesData) {
                try {
                    var paises = JSON.parse(paisesData.textContent);
                    VersionSelector.init('#version-selector', paises);
                } catch (e) {
                    console.warn('[PageRouter] Error al parsear países:', e);
                }
            }

            // ThemeManager ya está inicializado
            ThemeManager.listenBootstrapChanges();
        },

        /**
         * Inicializa módulos para admin/index.php.
         * @private
         */
        _initAdminIndex: function() {
            ThemeManager.listenBootstrapChanges();
        },

        /**
         * Inicializa módulos para admin/crear.php.
         * @private
         */
        _initAdminCrear: function() {
            // EstrofaManager para el contenedor de estrofas dinámicas
            EstrofaManager.init('#estrofas-container');

            // ThemeManager
            ThemeManager.listenBootstrapChanges();
        },

        /**
         * Inicializa módulos para admin/editar.php.
         * @private
         */
        _initAdminEditar: function() {
            // EstrofaManager con datos precargados
            var dataEl = document.getElementById('estrofas-data');
            var initialData = [];
            if (dataEl) {
                try {
                    initialData = JSON.parse(dataEl.textContent);
                } catch (e) {
                    console.warn('[PageRouter] Error al parsear estrofas:', e);
                }
            }
            EstrofaManager.init('#estrofas-container', initialData);

            // ThemeManager
            ThemeManager.listenBootstrapChanges();
        },

        /**
         * Inicializa módulos para login.php.
         * @private
         */
        _initLogin: function() {
            ThemeManager.listenBootstrapChanges();
        },
    };

    // ====================================================================
    // 9. EXPOSICIÓN PÚBLICA
    // ====================================================================

    // Solo exponer lo necesario, mantener el resto privado
    return {
        // Configuración
        CONFIG: CONFIG,

        // Módulos
        ThemeManager: ThemeManager,
        LiveSearch: LiveSearch,
        EstrofaManager: EstrofaManager,
        VersionSelector: VersionSelector,
        FullscreenManager: FullscreenManager,
        UIUtils: UIUtils,
        PageRouter: PageRouter,

        // Atajos
        init: function() { ThemeManager.init(); },
        initPage: function(pageName) { PageRouter.init(pageName); },
        toggleTheme: function() { return ThemeManager.toggle(); },
    };

})();