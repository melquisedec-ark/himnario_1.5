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
    // 3. MÓDULO: BÚSQUEDA EN VIVO (LiveSearch) — VERSIÓN AJAX
    // Usado en: index.php
    //
    // CAMBIOS v1.5 → v1.6:
    //   - .search() ahora usa fetch() en lugar de form.submit()
    //   - history.pushState() actualiza la URL sin recargar
    //   - popstate manejado para navegación atrás/adelante
    //   - AbortController para cancelar peticiones en curso
    //   - Skeleton/spinner durante la carga
    //   - Animaciones de transición en resultados
    // ====================================================================

    /**
     * LiveSearch — Búsqueda en vivo con debounce y AJAX.
     *
     * USO EN HTML:
     *   <input type="text" id="q" ...>
     *   <div id="search-results-wrapper">...</div>
     *   <script>
     *     Himnario.LiveSearch.init('input[name="q"]', '#search-results-wrapper');
     *   </script>
     *
     * MÉTODOS EXPUESTOS:
     *   .init(inputSelector, wrapperSelector) → Inicializa el buscador
     *   .search(query)                         → Ejecuta búsqueda AJAX
     *   .triggerSearch()                       → Lee todos los filtros y busca
     *   .cancel()                              → Cancela petición en curso
     *   .getLastQuery()                        → Último término buscado
     */
    const LiveSearch = {
        _timeout: null,
        _lastQuery: '',
        _lastUrl: '',
        _abortController: null,
        _input: null,
        _wrapper: null,
        _form: null,
        _popstateHandler: null,

        /**
         * Inicializa el buscador en vivo.
         * @param {string} inputSelector - Selector CSS del input de búsqueda
         * @param {string} [wrapperSelector] - Selector del wrapper de resultados
         * @returns {void}
         */
        init: function(inputSelector, wrapperSelector) {
            const input = document.querySelector(inputSelector);
            if (!input) {
                console.warn('[LiveSearch] No se encontró el input:', inputSelector);
                return;
            }

            this._input = input;
            this._wrapper = wrapperSelector
                ? document.querySelector(wrapperSelector)
                : document.getElementById('search-results-wrapper');
            this._form = input.closest('form');

            // --- Evento: input con debounce ---
            input.addEventListener('input', (e) => {
                const value = e.target.value.trim();
                this._onInput(value);
            });

            // --- Evento: Enter (búsqueda inmediata) ---
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._cancelTimeout();
                    this.search(e.target.value.trim());
                }
            });

            // --- Evento: blur (si hay algo, buscar) ---
            input.addEventListener('blur', (e) => {
                const value = e.target.value.trim();
                if (value.length >= CONFIG.search.minLength) {
                    this._cancelTimeout();
                    this.search(value);
                }
            });

            // --- popstate: navegación atrás/adelante ---
            this._popstateHandler = (e) => this._handlePopState(e);
            window.addEventListener('popstate', this._popstateHandler);

            console.log('[LiveSearch] Inicializado en:', inputSelector);
        },

        /**
         * Maneja el input con debounce.
         * @param {string} query
         * @private
         */
        _onInput: function(query) {
            this._cancelTimeout();

            // Vacío → buscar sin filtro (debounce corto)
            if (query.length === 0) {
                this._timeout = setTimeout(() => this.search(''), 100);
                return;
            }

            // Mínimo de caracteres antes de buscar
            if (query.length >= CONFIG.search.minLength) {
                this._timeout = setTimeout(
                    () => this.search(query),
                    CONFIG.search.debounceMs
                );
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
         * Construye la URL del endpoint con los parámetros actuales.
         * @param {string} query - Término de búsqueda
         * @returns {string} URL completa para fetch
         * @private
         */
        _buildSearchUrl: function(query) {
            const params = new URLSearchParams();

            if (query) params.set('q', query);

            const catEl = document.getElementById('input-categoria');
            const catVal = catEl ? catEl.value : '0';
            if (catVal !== '0' && catVal !== '') params.set('categoria', catVal);

            const tipoEl = document.getElementById('input-tipo');
            const tipoVal = tipoEl ? tipoEl.value : '0';
            if (tipoVal !== '0' && tipoVal !== '') params.set('tipo', tipoVal);

            const tonEl = document.getElementById('input-tonalidad');
            const tonVal = tonEl ? tonEl.value : '';
            if (tonVal !== '') params.set('tonalidad', tonVal);

            return 'api_search.php?' + params.toString();
        },

        /**
         * Ejecuta la búsqueda vía AJAX.
         * @param {string} query - Término de búsqueda
         * @returns {void}
         */
        search: function(query) {
            query = query || '';
            this._lastQuery = query;

            // Actualizar el input visual
            if (this._input) {
                this._input.value = query;
            }

            // Construir URL del endpoint
            const url = this._buildSearchUrl(query);

            // Evitar fetch duplicado para la misma URL
            if (url === this._lastUrl && !this._forceSearch) return;
            this._lastUrl = url;
            this._forceSearch = false;

            // Cancelar petición anterior (si existe)
            if (this._abortController) {
                this._abortController.abort();
            }
            this._abortController = new AbortController();

            // Mostrar estado de carga
            this._showLoading();

            // Actualizar la URL del navegador (pushState)
            this._pushState(query);

            // Fetch al endpoint
            fetch(url, {
                signal: this._abortController.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Error HTTP: ' + response.status);
                }
                return response.text();
            })
            .then((html) => {
                this._hideLoading();
                this._updateResults(html);

                // Restaurar modo de vista guardado
                this._restoreViewMode();

                // Disparar evento personalizado
                document.dispatchEvent(new CustomEvent('searchExecuted', {
                    detail: { query: query, timestamp: Date.now(), ajax: true },
                }));
            })
            .catch((err) => {
                // Ignorar errores de abort (cancelación intencional)
                if (err.name === 'AbortError') return;

                this._hideLoading();
                console.error('[LiveSearch] Error en fetch:', err);

                // Si el fetch falla, mostrar toast de error
                if (window.Himnario && Himnario.UIUtils) {
                    Himnario.UIUtils.showToast(
                        'Error al buscar. Verifica la conexión.',
                        'error',
                        4000
                    );
                }

                // Fallback seguro: submit tradicional si fetch no funciona
                this._fallbackSubmit(query);
            });
        },

        /**
         * Lee todos los filtros del formulario y ejecuta la búsqueda.
         * Útil para llamar desde pills de filtro o el submit del form.
         * @returns {void}
         */
        triggerSearch: function() {
            const query = this._input ? this._input.value.trim() : '';
            this._forceSearch = true;
            this.search(query);
        },

        /**
         * Actualiza la URL del navegador con history.pushState.
         * @param {string} query
         * @private
         */
        _pushState: function(query) {
            const params = new URLSearchParams();

            if (query) params.set('q', query);

            const catVal = document.getElementById('input-categoria')?.value;
            if (catVal && catVal !== '0') params.set('categoria', catVal);

            const tipoVal = document.getElementById('input-tipo')?.value;
            if (tipoVal && tipoVal !== '0') params.set('tipo', tipoVal);

            const tonVal = document.getElementById('input-tonalidad')?.value;
            if (tonVal) params.set('tonalidad', tonVal);

            const queryString = params.toString();
            const newUrl = queryString
                ? window.location.pathname + '?' + queryString
                : window.location.pathname;

            // Solo hacer pushState si la URL cambió
            if (newUrl !== window.location.href) {
                const state = {
                    q: query,
                    categoria: catVal || '0',
                    tipo: tipoVal || '0',
                    tonalidad: tonVal || '',
                };
                try {
                    history.pushState(state, '', newUrl);
                } catch (e) {
                    // Algunos navegadores restringen pushState en file://
                    console.warn('[LiveSearch] pushState no soportado:', e.message);
                }
            }
        },

        /**
         * Maneja el evento popstate (navegación atrás/adelante).
         * @param {PopStateEvent} event
         * @private
         */
        _handlePopState: function(event) {
            // Leer parámetros de la URL actual
            const urlParams = new URLSearchParams(window.location.search);
            const q = urlParams.get('q') || '';
            const categoria = urlParams.get('categoria') || '0';
            const tipo = urlParams.get('tipo') || '0';
            const tonalidad = urlParams.get('tonalidad') || '';

            // Restaurar valores en los inputs ocultos
            const catInput = document.getElementById('input-categoria');
            if (catInput) catInput.value = categoria;

            const tipoInput = document.getElementById('input-tipo');
            if (tipoInput) tipoInput.value = tipo;

            const tonInput = document.getElementById('input-tonalidad');
            if (tonInput) tonInput.value = tonalidad;

            // Restaurar el input de búsqueda
            if (this._input) {
                this._input.value = q;
            }

            // Actualizar pills visualmente
            this._syncPillsFromInputs();

            // Forzar búsqueda (puede ser la misma query pero diferentes filtros)
            this._forceSearch = true;
            this.search(q);
        },

        /**
         * Sincroniza el estado visual de los pills con los inputs ocultos.
         * @private
         */
        _syncPillsFromInputs: function() {
            const filters = ['categoria', 'tipo', 'tonalidad'];
            filters.forEach(function(name) {
                const input = document.getElementById('input-' + name);
                if (!input) return;
                const value = input.value;
                const group = document.querySelector(
                    '.filter-pills-group[data-filter="' + name + '"]'
                );
                if (!group) return;
                group.querySelectorAll('.filter-pill').forEach(function(pill) {
                    pill.classList.toggle('active', pill.dataset.value === String(value));
                });
            });
        },

        /**
         * Muestra el estado de carga (skeleton/spinner) en los resultados.
         * @private
         */
        _showLoading: function() {
            if (!this._wrapper) return;

            // Pequeño fade out del contenido actual
            this._wrapper.style.opacity = '0.4';
            this._wrapper.style.transform = 'translateY(4px)';

            // Reemplazar contenido con spinner después de la transición
            var self = this;
            setTimeout(function() {
                if (!self._wrapper) return;
                self._wrapper.innerHTML =
                    '<div class="loading-state">' +
                        '<div class="spinner"></div>' +
                        '<span style="margin-top:12px;color:var(--text-muted);font-size:0.9rem;">' +
                            'Buscando himnos...' +
                        '</span>' +
                    '</div>';
                self._wrapper.style.opacity = '1';
                self._wrapper.style.transform = 'translateY(0)';
            }, 150);
        },

        /**
         * Oculta el estado de carga (llamado después de recibir respuesta).
         * @private
         */
        _hideLoading: function() {
            // No hace nada; _updateResults reemplaza el contenido
        },

        /**
         * Actualiza el DOM con el HTML de resultados recibido.
         * Incluye animación de transición.
         * @param {string} html - HTML fragment de resultados
         * @private
         */
        _updateResults: function(html) {
            if (!this._wrapper) return;

            // Aplicar fade out rápido
            this._wrapper.style.opacity = '0';
            this._wrapper.style.transform = 'translateY(10px)';

            var self = this;
            setTimeout(function() {
                if (!self._wrapper) return;

                // Reemplazar contenido
                self._wrapper.innerHTML = html;

                // Forzar reflow para reiniciar la animación
                void self._wrapper.offsetHeight;

                // Fade in con translate
                self._wrapper.style.opacity = '1';
                self._wrapper.style.transform = 'translateY(0)';

                // Si el IntersectionObserver de scrollReveal existe, re-aplicarlo
                if (window.Himnario && Himnario.PageRouter) {
                    // Pequeño helper: revelar las nuevas cards gradualmente
                    self._animateNewCards();
                }
            }, 200);
        },

        /**
         * Anima las nuevas cards con un pequeño retardo escalonado.
         * @private
         */
        _animateNewCards: function() {
            var cards = document.querySelectorAll('.himno-card');
            if (!cards.length) return;

            // Resetear estilos inline por si acaso
            cards.forEach(function(card, index) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(15px)';
                card.style.transition =
                    'opacity 0.4s ease, transform 0.4s ease';
                // Stagger: cada card aparece con un retardo incremental
                setTimeout(function() {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 60 + (index * 30));
            });

            // Guardar referencia para cleanup
            this._cardsAnimated = cards;
        },

        /**
         * Restaura el modo de vista (grid/lista) guardado en localStorage.
         * @private
         */
        _restoreViewMode: function() {
            var savedView = localStorage.getItem('himnario_view_mode');
            if (savedView === 'list') {
                var container = document.getElementById('results-container');
                var btn = document.getElementById('view-toggle');
                var icon = document.getElementById('view-toggle-icon');
                var text = document.getElementById('view-toggle-text');
                if (container && btn && icon && text) {
                    container.classList.add('view-list');
                    icon.textContent = '⊞';
                    text.textContent = 'Grid';
                    btn.classList.add('active');
                }
            }
        },

        /**
         * Fallback: submit tradicional si fetch no está disponible o falla.
         * @param {string} query
         * @private
         */
        _fallbackSubmit: function(query) {
            if (this._form) {
                // Actualizar el input oculto q si existe
                var qInput = this._form.querySelector('input[name="q"]');
                if (qInput) qInput.value = query;
                this._form.submit();
            }
        },

        cancel: function() {
            this._cancelTimeout();
            if (this._abortController) {
                this._abortController.abort();
            }
        },

        getLastQuery: function() {
            return this._lastQuery;
        },

        /**
         * Libera recursos (llamar al destruir la página).
         */
        destroy: function() {
            this._cancelTimeout();
            if (this._abortController) {
                this._abortController.abort();
            }
            if (this._popstateHandler) {
                window.removeEventListener('popstate', this._popstateHandler);
            }
            this._input = null;
            this._wrapper = null;
            this._form = null;
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
                '<div class="d-flex align-items-center gap-1">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" ' +
                'onclick="Himnario.EstrofaManager.move(this, -1)" title="Subir">↑</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" ' +
                'onclick="Himnario.EstrofaManager.move(this, 1)" title="Bajar">↓</button>' +
                '<span class="badge bg-dark me-1">#' + num + '</span>' +
                '<button type="button" class="btn-eliminar btn btn-sm btn-outline-danger" ' +
                'onclick="this.closest(\'.estrofa-box\').remove(); Himnario.EstrofaManager._reindex();">✕</button>' +
                '</div>' +
                '</div>' +
                '<input type="hidden" name="tipo_estrofa[]" value="' + tipo + '">' +
                '<textarea name="contenido_estrofa[]" class="form-control estrofa-textarea" rows="4" ' +
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
                        tipo: block.querySelector('input[name="tipo_estrofa[]"]')?.value || 'estrofa',
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
         * Mueve una estrofa hacia arriba (intercambia con la anterior).
         * @param {number} index - Índice de la estrofa a mover (0-based)
         * @returns {boolean} true si se movió
         */
        moveUp: function(index) {
            const blocks = this._container.querySelectorAll('.estrofa-box');
            if (index <= 0 || index >= blocks.length) return false;
            // Intercambiar nodos DOM
            const current = blocks[index];
            const previous = blocks[index - 1];
            this._container.insertBefore(current, previous);
            this._reindex();
            this._scrollToElement(current);
            return true;
        },

        /**
         * Mueve una estrofa hacia abajo (intercambia con la siguiente).
         * @param {number} index - Índice de la estrofa a mover (0-based)
         * @returns {boolean} true si se movió
         */
        moveDown: function(index) {
            const blocks = this._container.querySelectorAll('.estrofa-box');
            if (index < 0 || index >= blocks.length - 1) return false;
            const current = blocks[index];
            const next = blocks[index + 1];
            this._container.insertBefore(next, current);
            this._reindex();
            this._scrollToElement(current);
            return true;
        },

        /**
         * Hace scroll suave hasta un elemento.
         * @private
         */
        _scrollToElement: function(el) {
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },

        /**
         * Mueve una estrofa desde un botón (dirección -1 arriba, 1 abajo).
         * @param {HTMLElement} btn - Botón que disparó la acción
         * @param {number} direccion - -1 para subir, 1 para bajar
         * @returns {boolean} true si se movió
         */
        move: function(btn, direccion) {
            const box = btn.closest('.estrofa-box');
            const container = this._container;
            if (!box || !container) return false;
            if (direccion === -1 && box.previousElementSibling) {
                container.insertBefore(box, box.previousElementSibling);
            } else if (direccion === 1 && box.nextElementSibling) {
                container.insertBefore(box.nextElementSibling, box);
            } else {
                return false;
            }
            this._reindex();
            return true;
        },

        /**
         * Reindexa las estrofas después de una eliminación o reordenamiento.
         * @private
         */
        _reindex: function() {
            this._count = 0;
            const blocks = this._container.querySelectorAll('.estrofa-box');
            blocks.forEach((block, index) => {
                this._count = index + 1;
                const badge = block.querySelector('.badge.bg-dark');
                if (badge) badge.textContent = '#' + (index + 1);
                // Actualizar el atributo dataset
                block.dataset.orden = index + 1;
                // Actualizar el hidden input de orden si existe
                const ordenInput = block.querySelector('input[name="orden[]"]');
                if (ordenInput) ordenInput.value = index + 1;
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
    // 8. MÓDULO: ATAJOS DE TECLADO (KeyboardShortcuts)
    // Usado en: index.php, admin/*.php
    // ====================================================================

    /**
     * KeyboardShortcuts — Atajos de teclado globales.
     *
     * Atajos:
     *   Ctrl+K / ⌘K → Enfocar búsqueda
     *   ?           → Mostrar ayuda de atajos
     */
    const KeyboardShortcuts = {
        _initialized: false,

        /**
         * Inicializa los atajos de teclado.
         * @returns {void}
         */
        init: function() {
            if (this._initialized) return;
            this._initialized = true;

            document.addEventListener('keydown', function(e) {
                // Ctrl+K / ⌘K → enfocar búsqueda
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    var q = document.querySelector('input[name="q"]');
                    if (q) {
                        q.focus();
                        q.select();
                    }
                }

                // ? → mostrar ayuda de atajos (solo si no está en un input)
                if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.target.matches('input,textarea,select')) {
                    e.preventDefault();
                    // Usar UIUtils si está disponible
                    if (typeof UIUtils !== 'undefined' && UIUtils.showToast) {
                        UIUtils.showToast('⌨️ Ctrl+K: Buscar | ?: Ayuda', 'info', 3000);
                    } else {
                        // Fallback a alert
                        alert('Atajos de teclado:\n\nCtrl+K — Buscar himno\n? — Mostrar esta ayuda');
                    }
                }

                // Escape → cerrar modales/paneles si existen
                if (e.key === 'Escape') {
                    // Cerrar dropdowns de Bootstrap
                    var openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                    openDropdowns.forEach(function(d) {
                        d.classList.remove('show');
                    });
                }
            });

            console.log('[KeyboardShortcuts] Inicializado');
        },
    };

    // ====================================================================
    // 9. INICIALIZACIÓN POR PÁGINA (PageRouter)
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
            // Búsqueda en vivo con AJAX (fetch + history.pushState)
            // Nota: el segundo parámetro es #search-results-wrapper (no #results-container)
            // porque el wrapper completo se reemplaza con el HTML de api_search.php
            if (document.querySelector('input[name="q"]')) {
                LiveSearch.init(
                    'input[name="q"]',
                    '#search-results-wrapper'
                );
            }

            // ThemeManager ya está inicializado arriba
            // Escuchar cambios de tema desde Bootstrap
            ThemeManager.listenBootstrapChanges();

            // Atajos de teclado
            KeyboardShortcuts.init();

            // Scroll progresivo con IntersectionObserver (solo en carga inicial)
            this._initScrollReveal();
        },

        /**
         * Revela cards progresivamente al hacer scroll.
         * @private
         */
        _initScrollReveal: function() {
            var cards = document.querySelectorAll('.himno-card');
            if (!cards.length) return;

            if ('IntersectionObserver' in window && cards.length > 50) {
                // Preparar animación inicial
                cards.forEach(function(card) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                });

                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                            observer.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '100px' });

                cards.forEach(function(card) {
                    observer.observe(card);
                });
            } else {
                // Fallback: mostrar todo inmediatamente
                cards.forEach(function(card) {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                });
            }
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
            KeyboardShortcuts.init();
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

            // Atajos de teclado
            KeyboardShortcuts.init();
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

            // Atajos de teclado
            KeyboardShortcuts.init();
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

    // ====================================================================
    // 10. FUNCIÓN GLOBAL: toggleView (accesible desde onclick en HTML)
    // ====================================================================

    /**
     * toggleView — Alterna entre vista grid y lista para el contenedor
     * de resultados. Se expone como Himnario.toggleView() para que pueda
     * ser llamada desde los onclick generados por api_search.php.
     * @returns {void}
     */
    function toggleView() {
        const container = document.getElementById('results-container');
        if (!container) return;

        const btn = document.getElementById('view-toggle');
        const icon = document.getElementById('view-toggle-icon');
        const text = document.getElementById('view-toggle-text');
        if (!btn || !icon || !text) return;

        container.classList.toggle('view-list');
        const isList = container.classList.contains('view-list');

        icon.textContent = isList ? '⊞' : '☰';
        text.textContent = isList ? 'Grid' : 'Lista';
        btn.classList.toggle('active', isList);

        try {
            localStorage.setItem('himnario_view_mode', isList ? 'list' : 'grid');
        } catch (e) { /* ignorar */ }
    }

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
        KeyboardShortcuts: KeyboardShortcuts,

        // Atajos
        init: function() { ThemeManager.init(); },
        initPage: function(pageName) { PageRouter.init(pageName); },
        toggleTheme: function() { return ThemeManager.toggle(); },
        toggleView: toggleView,
    };

})();