(() => {
    'use strict';

    const MOL_UNIT = 1_000_000;
    const MIN_ZOOM = 1;
    const MAX_ZOOM = 3;
    const ZOOM_STEP = 0.25;
    const SAVE_DELAY_MS = 1_200;
    const MODES = new Set(['webtoon', 'paged']);
    const DIRECTIONS = new Set(['rtl', 'ltr']);
    const SHAPES = new Set(['ellipse', 'rounded_rect', 'rect', 'cloud', 'none', 'burst', 'impact']);
    const FONT_FAMILIES = {
        'noto-sans-arabic': '"Noto Sans Arabic", "Segoe UI", sans-serif',
        cairo: 'Cairo, "Noto Sans Arabic", "Segoe UI", sans-serif',
        tajawal: 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
        'noto-kufi-arabic': '"Noto Kufi Arabic", "Segoe UI", sans-serif',
        'sfx-display-1': 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
    };
    const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    function clamp(value, minimum, maximum) {
        return Math.min(maximum, Math.max(minimum, value));
    }

    function finiteNumber(value, fallback, minimum = -Infinity, maximum = Infinity) {
        return 'number' === typeof value && Number.isFinite(value)
            ? clamp(value, minimum, maximum)
            : fallback;
    }

    function integer(value, fallback, minimum = -Infinity, maximum = Infinity) {
        return Number.isInteger(value) ? clamp(value, minimum, maximum) : fallback;
    }

    function normalizeMode(value, fallback = 'webtoon') {
        return MODES.has(value) ? value : fallback;
    }

    function normalizeDirection(value, fallback = 'rtl') {
        return DIRECTIONS.has(value) ? value : fallback;
    }

    function asPercentage(value) {
        return `${(integer(value, 0, 0, MOL_UNIT) / MOL_UNIT) * 100}%`;
    }

    function unitToPixels(value, imageWidth) {
        return (finiteNumber(value, 0) / MOL_UNIT) * Math.max(1, imageWidth);
    }

    function isColor(value) {
        return 'string' === typeof value && /^#[0-9a-f]{6}$/i.test(value);
    }

    function color(value, fallback) {
        return isColor(value) ? value : fallback;
    }

    function rgba(hex, opacity) {
        const normalized = color(hex, '#000000').slice(1);
        const red = Number.parseInt(normalized.slice(0, 2), 16);
        const green = Number.parseInt(normalized.slice(2, 4), 16);
        const blue = Number.parseInt(normalized.slice(4, 6), 16);

        return `rgba(${red}, ${green}, ${blue}, ${finiteNumber(opacity, 1, 0, 1)})`;
    }

    function defaultStyle(elementType) {
        const styles = {
            bubble: {
                fontId: 'noto-sans-arabic',
                fontSizeUnit: 30_000,
                fontWeight: 600,
                lineHeight: 1.35,
                textAlign: 'center',
                color: '#111111',
                backgroundColor: '#FFFFFF',
                backgroundOpacity: 0.96,
                borderColor: '#151515',
                borderWidthUnit: 1_500,
                paddingUnit: 10_000,
                shape: 'ellipse',
            },
            narration: {
                fontId: 'noto-sans-arabic',
                fontSizeUnit: 25_000,
                fontWeight: 600,
                lineHeight: 1.4,
                textAlign: 'center',
                color: '#17130F',
                backgroundColor: '#F4E9D5',
                backgroundOpacity: 0.96,
                borderColor: '#17130F',
                borderWidthUnit: 1_200,
                paddingUnit: 9_000,
                shape: 'rounded_rect',
            },
            free_text: {
                fontId: 'noto-sans-arabic',
                fontSizeUnit: 28_000,
                fontWeight: 600,
                lineHeight: 1.35,
                textAlign: 'center',
                color: '#111111',
                backgroundColor: '#FFFFFF',
                backgroundOpacity: 0,
                borderColor: '#111111',
                borderWidthUnit: 0,
                paddingUnit: 0,
                shape: 'none',
            },
            sfx: {
                fontId: 'sfx-display-1',
                fontSizeUnit: 52_000,
                fontWeight: 900,
                lineHeight: 1.05,
                textAlign: 'center',
                color: '#FFFFFF',
                backgroundColor: '#B5231C',
                backgroundOpacity: 0,
                borderColor: '#171311',
                borderWidthUnit: 0,
                paddingUnit: 4_000,
                shape: 'none',
            },
        };

        return styles[elementType] || styles.free_text;
    }

    function normalizeStyle(elementType, candidate) {
        const base = defaultStyle(elementType);
        const style = candidate && 'object' === typeof candidate && !Array.isArray(candidate)
            ? candidate
            : {};
        const fontId = Object.hasOwn(FONT_FAMILIES, style.fontId) ? style.fontId : base.fontId;
        const shape = SHAPES.has(style.shape) ? style.shape : base.shape;
        const textAlign = ['start', 'center', 'end'].includes(style.textAlign)
            ? style.textAlign
            : base.textAlign;

        return {
            fontId,
            fontSizeUnit: integer(style.fontSizeUnit, base.fontSizeUnit, 1_000, 200_000),
            fontWeight: [400, 500, 600, 700, 800, 900].includes(style.fontWeight)
                ? style.fontWeight
                : base.fontWeight,
            lineHeight: finiteNumber(style.lineHeight, base.lineHeight, 1, 2.5),
            textAlign,
            color: color(style.color, base.color),
            backgroundColor: color(style.backgroundColor, base.backgroundColor),
            backgroundOpacity: finiteNumber(style.backgroundOpacity, base.backgroundOpacity, 0, 1),
            borderColor: color(style.borderColor, base.borderColor),
            borderWidthUnit: integer(style.borderWidthUnit, base.borderWidthUnit, 0, 50_000),
            paddingUnit: integer(style.paddingUnit, base.paddingUnit, 0, 100_000),
            shape,
            strokeColor: isColor(style.strokeColor) ? style.strokeColor : null,
            strokeWidthUnit: integer(style.strokeWidthUnit, 0, 0, 50_000),
            shadow: style.shadow && 'object' === typeof style.shadow ? style.shadow : null,
            scaleX: finiteNumber(style.scaleX, 1, 0.5, 2),
            scaleY: finiteNumber(style.scaleY, 1, 0.5, 2),
            burst: style.burst && 'object' === typeof style.burst ? style.burst : null,
        };
    }

    function createSvgNode(name) {
        return document.createElementNS(SVG_NAMESPACE, name);
    }

    function burstPoints(pointCount, depth) {
        const center = 500;
        const innerRadius = 480 - (finiteNumber(depth, 0.35, 0, 1) * 260);
        const points = [];
        for (let index = 0; index < pointCount * 2; index += 1) {
            const angle = (Math.PI * 2 * index) / (pointCount * 2) - Math.PI / 2;
            const radius = 0 === index % 2 ? 480 : innerRadius;
            points.push(`${center + Math.cos(angle) * radius},${center + Math.sin(angle) * radius}`);
        }

        return points.join(' ');
    }

    function appendShape(svg, style) {
        switch (style.shape) {
            case 'ellipse': {
                const ellipse = createSvgNode('ellipse');
                ellipse.setAttribute('cx', '500');
                ellipse.setAttribute('cy', '460');
                ellipse.setAttribute('rx', '475');
                ellipse.setAttribute('ry', '430');
                svg.append(ellipse);
                const tail = createSvgNode('path');
                tail.setAttribute('d', 'M 690 805 Q 785 955 885 990 Q 805 850 820 735 Z');
                svg.append(tail);
                break;
            }
            case 'rounded_rect':
            case 'rect': {
                const rectangle = createSvgNode('rect');
                rectangle.setAttribute('x', '20');
                rectangle.setAttribute('y', '20');
                rectangle.setAttribute('width', '960');
                rectangle.setAttribute('height', '960');
                rectangle.setAttribute('rx', 'rounded_rect' === style.shape ? '110' : '0');
                svg.append(rectangle);
                break;
            }
            case 'cloud': {
                const cloud = createSvgNode('path');
                cloud.setAttribute('d', 'M95 580 C20 430 130 300 265 315 C265 150 460 100 545 225 C650 105 855 185 840 350 C980 360 1010 555 890 625 C920 790 735 890 625 790 C520 930 295 860 285 720 C165 745 70 685 95 580 Z');
                svg.append(cloud);
                break;
            }
            case 'burst':
            case 'impact': {
                const polygon = createSvgNode('polygon');
                const allowedPoints = [8, 12, 16, 24];
                const requested = integer(style.burst?.points, 'impact' === style.shape ? 16 : 12);
                const pointCount = allowedPoints.includes(requested) ? requested : 12;
                polygon.setAttribute('points', burstPoints(pointCount, style.burst?.depth));
                svg.append(polygon);
                break;
            }
            default:
                break;
        }
    }

    function createShapeLayer(style, imageWidth) {
        if ('none' === style.shape) {
            return null;
        }
        const svg = createSvgNode('svg');
        svg.classList.add('mol-element-shape');
        svg.setAttribute('viewBox', '0 0 1000 1000');
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('aria-hidden', 'true');
        appendShape(svg, style);
        for (const node of svg.querySelectorAll('ellipse, rect, path, polygon')) {
            node.setAttribute('fill', style.backgroundColor);
            node.setAttribute('fill-opacity', String(style.backgroundOpacity));
            node.setAttribute('stroke', style.borderColor);
            node.setAttribute('stroke-width', String(unitToPixels(style.borderWidthUnit, imageWidth)));
            node.setAttribute('vector-effect', 'non-scaling-stroke');
            node.setAttribute('stroke-linejoin', 'round');
        }

        return svg;
    }

    function createOverlayNode(element, imageWidth, targetLanguage = 'ar') {
        const elementType = ['bubble', 'narration', 'free_text', 'sfx'].includes(element?.element_type)
            ? element.element_type
            : 'free_text';
        const style = normalizeStyle(elementType, element?.style);
        const node = document.createElement('article');
        node.className = `mol-overlay-element mol-overlay-element--${elementType}`;
        node.dataset.elementId = String(integer(element?.id, 0, 0));
        node.dataset.elementType = elementType;
        node.style.left = asPercentage(element?.x_unit);
        node.style.top = asPercentage(element?.y_unit);
        node.style.width = asPercentage(integer(element?.w_unit, 1, 1, MOL_UNIT));
        node.style.height = asPercentage(integer(element?.h_unit, 1, 1, MOL_UNIT));
        node.style.zIndex = String(integer(element?.z_index, 0, -1_000, 10_000));
        const rotation = integer(element?.rotation_mdeg, 0, -360_000, 360_000) / 1_000;
        node.style.transform = `rotate(${rotation}deg) scale(${style.scaleX}, ${style.scaleY})`;

        const shape = createShapeLayer(style, imageWidth);
        if (shape) {
            node.append(shape);
        }

        const text = document.createElement('p');
        text.className = 'mol-element-text';
        text.textContent = 'string' === typeof element?.content ? element.content : '';
        text.lang = 'string' === typeof element?.target_lang ? element.target_lang : targetLanguage;
        text.dir = 'rtl';
        text.style.fontFamily = FONT_FAMILIES[style.fontId];
        text.style.fontSize = `${unitToPixels(style.fontSizeUnit, imageWidth)}px`;
        text.style.fontWeight = String(style.fontWeight);
        text.style.lineHeight = String(style.lineHeight);
        text.style.textAlign = style.textAlign;
        text.style.color = style.color;
        text.style.padding = `${unitToPixels(style.paddingUnit, imageWidth)}px`;
        if (style.strokeColor && style.strokeWidthUnit > 0) {
            text.style.webkitTextStrokeColor = style.strokeColor;
            text.style.webkitTextStrokeWidth = `${unitToPixels(style.strokeWidthUnit, imageWidth)}px`;
            text.style.paintOrder = 'stroke fill';
        }
        if (style.shadow) {
            const x = unitToPixels(finiteNumber(style.shadow.xUnit, 0, -50_000, 50_000), imageWidth);
            const y = unitToPixels(finiteNumber(style.shadow.yUnit, 0, -50_000, 50_000), imageWidth);
            const blur = unitToPixels(finiteNumber(style.shadow.blurUnit, 0, 0, 50_000), imageWidth);
            text.style.textShadow = `${x}px ${y}px ${blur}px ${rgba(style.shadow.color, style.shadow.opacity)}`;
        }
        node.append(text);

        return node;
    }

    function createProgressPayload(chapterId, pageIndex, progressUnit, readerMode) {
        return {
            chapter_id: integer(chapterId, 0, 1),
            page_index: integer(pageIndex, 0, 0),
            progress_unit: integer(progressUnit, 0, 0, MOL_UNIT),
            reader_mode: normalizeMode(readerMode),
        };
    }

    function progressStorageKey(chapterId) {
        return `mol_progress_${integer(chapterId, 0, 0)}`;
    }

    function safeStorageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    }

    function safeStorageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Reading still works when storage is blocked.
        }
    }

    function storedJson(key) {
        const value = safeStorageGet(key);
        if (!value) {
            return null;
        }
        try {
            const parsed = JSON.parse(value);
            return parsed && 'object' === typeof parsed ? parsed : null;
        } catch {
            return null;
        }
    }

    function parseReaderData() {
        const source = document.getElementById('mol-reader-data');
        if (!source) {
            return null;
        }
        try {
            const data = JSON.parse(source.textContent || '{}');
            return data && 'object' === typeof data ? data : null;
        } catch {
            return null;
        }
    }

    function initReader() {
        const root = document.querySelector('[data-mol-reader]');
        const config = parseReaderData();
        if (!(root instanceof HTMLElement) || !config) {
            return;
        }

        const frames = Array.from(root.querySelectorAll('[data-mol-page]'))
            .filter((frame) => frame instanceof HTMLElement)
            .sort((left, right) => Number(left.dataset.pageIndex) - Number(right.dataset.pageIndex));
        if (0 === frames.length) {
            return;
        }

        const groups = new Map();
        for (const group of Array.isArray(config.elementGroups) ? config.elementGroups : []) {
            if (group && 'object' === typeof group) {
                groups.set(Number(group.page_id), Array.isArray(group.elements) ? group.elements : []);
            }
        }

        const renderedFrames = new WeakSet();
        const targetLanguage = 'string' === typeof config.targetLanguage ? config.targetLanguage : 'ar';
        const renderFrame = (frame, force = false) => {
            const layer = frame.querySelector('[data-mol-overlay-layer]');
            const image = frame.querySelector('.mol-reader-image');
            if (!(layer instanceof HTMLElement) || !(image instanceof HTMLImageElement)) {
                return;
            }
            if (renderedFrames.has(frame) && !force) {
                return;
            }
            const imageWidth = image.clientWidth || frame.clientWidth;
            if (imageWidth < 1) {
                image.addEventListener('load', () => renderFrame(frame, true), { once: true });
                return;
            }
            const elements = groups.get(Number(frame.dataset.pageId)) || [];
            const fragment = document.createDocumentFragment();
            for (const element of elements) {
                fragment.append(createOverlayNode(element, imageWidth, targetLanguage));
            }
            layer.replaceChildren(fragment);
            renderedFrames.add(frame);
        };

        const resizeObserver = 'ResizeObserver' in window
            ? new ResizeObserver((entries) => {
                for (const entry of entries) {
                    const frame = entry.target.closest('[data-mol-page]');
                    if (frame instanceof HTMLElement && renderedFrames.has(frame)) {
                        renderFrame(frame, true);
                    }
                }
            })
            : null;
        for (const frame of frames) {
            const image = frame.querySelector('.mol-reader-image');
            if (image instanceof HTMLImageElement && resizeObserver) {
                resizeObserver.observe(image);
            }
        }

        let translationVisible = Boolean(config.hasTranslation);
        const translationKey = `mol_translation_${targetLanguage}`;
        const storedTranslation = safeStorageGet(translationKey);
        if ('0' === storedTranslation || '1' === storedTranslation) {
            translationVisible = '1' === storedTranslation && Boolean(config.hasTranslation);
        }
        const translationButton = root.querySelector('[data-mol-translation-toggle]');
        const updateTranslation = (visible, persist = true) => {
            translationVisible = Boolean(visible && config.hasTranslation);
            root.dataset.translation = translationVisible ? 'on' : 'off';
            for (const frame of frames) {
                const layer = frame.querySelector('[data-mol-overlay-layer]');
                if (layer instanceof HTMLElement) {
                    layer.hidden = !translationVisible;
                }
            }
            if (translationButton instanceof HTMLButtonElement) {
                translationButton.setAttribute('aria-pressed', String(translationVisible));
                const label = translationButton.querySelector('span');
                if (label) {
                    label.textContent = translationVisible ? 'الترجمة ظاهرة' : 'الترجمة مخفية';
                }
            }
            if (persist) {
                safeStorageSet(translationKey, translationVisible ? '1' : '0');
            }
        };
        if (translationButton instanceof HTMLButtonElement) {
            translationButton.addEventListener('click', () => updateTranslation(!translationVisible));
        }

        const progressKey = progressStorageKey(config.chapterId);
        const localProgress = storedJson(progressKey);
        const serverProgress = config.initialProgress && 'object' === typeof config.initialProgress
            ? config.initialProgress
            : null;
        const initialProgress = config.isAuthenticated ? serverProgress : (localProgress || serverProgress);
        const initialPageIndex = integer(initialProgress?.page_index, 0, 0);
        let activePosition = Math.max(0, frames.findIndex(
            (frame) => Number(frame.dataset.pageIndex) === initialPageIndex
        ));
        const workModeKey = `mol_reader_mode_${integer(config.workId, 0, 0)}`;
        const storedMode = config.isAuthenticated ? null : safeStorageGet(workModeKey);
        let mode = normalizeMode(
            storedMode,
            normalizeMode(initialProgress?.reader_mode, normalizeMode(config.defaultMode))
        );
        const direction = normalizeDirection(config.direction);
        root.dataset.direction = direction;

        const progressStatus = root.querySelector('[data-mol-progress-status]');
        const pageCounter = root.querySelector('[data-mol-page-counter]');
        const previousPageButton = root.querySelector('[data-mol-page-previous]');
        const nextPageButton = root.querySelector('[data-mol-page-next]');
        const modeButtons = Array.from(root.querySelectorAll('[data-mol-mode]'));
        const zoomLevel = root.querySelector('[data-mol-zoom-level]');
        let saveTimer = 0;
        let saveSequence = 0;
        let scrollFrame = 0;
        let restoringProgress = true;

        const zoomStates = new WeakMap();
        function zoomState(frame) {
            if (!zoomStates.has(frame)) {
                zoomStates.set(frame, {
                    scale: 1,
                    x: 0,
                    y: 0,
                    pointers: new Map(),
                    gesture: null,
                });
            }
            return zoomStates.get(frame);
        }

        function constrainPan(frame, state) {
            const viewport = frame.querySelector('[data-mol-page-viewport]');
            if (!(viewport instanceof HTMLElement)) {
                return;
            }
            const maxX = (viewport.clientWidth * (state.scale - 1)) / 2;
            const maxY = (viewport.clientHeight * (state.scale - 1)) / 2;
            state.x = clamp(state.x, -maxX, maxX);
            state.y = clamp(state.y, -maxY, maxY);
        }

        function applyZoom(frame) {
            const surface = frame.querySelector('[data-mol-page-surface]');
            const viewport = frame.querySelector('[data-mol-page-viewport]');
            if (!(surface instanceof HTMLElement) || !(viewport instanceof HTMLElement)) {
                return;
            }
            const state = zoomState(frame);
            if (state.scale <= MIN_ZOOM) {
                state.scale = MIN_ZOOM;
                state.x = 0;
                state.y = 0;
            }
            constrainPan(frame, state);
            surface.style.transform = `translate3d(${state.x}px, ${state.y}px, 0) scale(${state.scale})`;
            viewport.classList.toggle('is-zoomed', state.scale > MIN_ZOOM);
            if (frame === frames[activePosition] && zoomLevel instanceof HTMLOutputElement) {
                zoomLevel.value = `${Math.round(state.scale * 100)}%`;
                zoomLevel.textContent = zoomLevel.value;
            }
        }

        function setZoom(frame, scale) {
            const state = zoomState(frame);
            state.scale = clamp(scale, MIN_ZOOM, MAX_ZOOM);
            applyZoom(frame);
        }

        function resetZoom(frame) {
            const state = zoomState(frame);
            state.scale = MIN_ZOOM;
            state.x = 0;
            state.y = 0;
            applyZoom(frame);
        }

        function updatePageControls() {
            const pageIndex = Number(frames[activePosition]?.dataset.pageIndex || 0);
            if (pageCounter) {
                pageCounter.textContent = `${pageIndex + 1} / ${frames.length}`;
            }
            if (previousPageButton instanceof HTMLButtonElement) {
                previousPageButton.disabled = activePosition <= 0;
            }
            if (nextPageButton instanceof HTMLButtonElement) {
                nextPageButton.disabled = activePosition >= frames.length - 1;
            }
            applyZoom(frames[activePosition]);
        }

        function pageProgress() {
            const frame = frames[activePosition];
            if (!frame || 'paged' === mode) {
                return 0;
            }
            const rect = frame.getBoundingClientRect();
            if (rect.height <= 0) {
                return 0;
            }
            const readerLine = window.innerHeight * 0.45;
            return Math.round(clamp((readerLine - rect.top) / rect.height, 0, 1) * MOL_UNIT);
        }

        function currentProgressPayload() {
            const pageIndex = Number(frames[activePosition]?.dataset.pageIndex || 0);
            return createProgressPayload(config.chapterId, pageIndex, pageProgress(), mode);
        }

        async function persistProgress(options = {}) {
            window.clearTimeout(saveTimer);
            saveTimer = 0;
            const payload = currentProgressPayload();
            safeStorageSet(progressKey, JSON.stringify({ ...payload, updated_at: new Date().toISOString() }));
            safeStorageSet(workModeKey, mode);
            if (!config.isAuthenticated || !config.restNonce || !config.progressEndpoint) {
                if (progressStatus && !options.silent) {
                    progressStatus.textContent = 'حُفظ موضع القراءة على هذا الجهاز';
                }
                return;
            }

            const sequence = ++saveSequence;
            if (progressStatus && !options.silent) {
                progressStatus.textContent = 'جارٍ حفظ موضع القراءة…';
            }
            try {
                const response = await window.fetch(config.progressEndpoint, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    keepalive: Boolean(options.keepalive),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce,
                    },
                    body: JSON.stringify(payload),
                });
                if (!response.ok) {
                    throw new Error(`Progress save failed with ${response.status}`);
                }
                if (progressStatus && !options.silent && sequence === saveSequence) {
                    progressStatus.textContent = 'حُفظ موضع القراءة';
                }
            } catch {
                if (progressStatus && !options.silent && sequence === saveSequence) {
                    progressStatus.textContent = 'تعذر مزامنة الموضع؛ بقي محفوظًا على هذا الجهاز';
                }
            }
        }

        function scheduleProgressSave() {
            if (restoringProgress) {
                return;
            }
            window.clearTimeout(saveTimer);
            if (progressStatus) {
                progressStatus.textContent = 'موضع القراءة تغيّر';
            }
            saveTimer = window.setTimeout(() => void persistProgress(), SAVE_DELAY_MS);
        }

        function renderNear(position) {
            for (const candidate of [position - 1, position, position + 1]) {
                if (frames[candidate]) {
                    renderFrame(frames[candidate]);
                }
            }
            const nearbyImage = frames[position + 1]?.querySelector('.mol-reader-image');
            if (nearbyImage instanceof HTMLImageElement) {
                nearbyImage.loading = 'eager';
                nearbyImage.fetchPriority = 'low';
            }
        }

        function setActivePosition(position, save = true) {
            const nextPosition = clamp(position, 0, frames.length - 1);
            if ('paged' === mode && nextPosition !== activePosition) {
                resetZoom(frames[activePosition]);
            }
            activePosition = nextPosition;
            if ('paged' === mode) {
                frames.forEach((frame, index) => {
                    frame.hidden = index !== activePosition;
                });
            }
            renderNear(activePosition);
            updatePageControls();
            if (save) {
                scheduleProgressSave();
            }
        }

        function applyMode(nextMode, persist = true) {
            mode = normalizeMode(nextMode, mode);
            root.dataset.mode = mode;
            for (const button of modeButtons) {
                if (button instanceof HTMLButtonElement) {
                    button.setAttribute('aria-pressed', String(button.dataset.molMode === mode));
                }
            }
            if ('paged' === mode) {
                setActivePosition(activePosition, false);
            } else {
                frames.forEach((frame) => { frame.hidden = false; });
                renderNear(activePosition);
                window.requestAnimationFrame(() => {
                    frames[activePosition].scrollIntoView({ block: 'start', behavior: 'auto' });
                });
            }
            if (persist) {
                safeStorageSet(workModeKey, mode);
                scheduleProgressSave();
            }
        }

        for (const button of modeButtons) {
            button.addEventListener('click', () => applyMode(button.dataset.molMode));
        }
        if (previousPageButton instanceof HTMLButtonElement) {
            previousPageButton.addEventListener('click', () => setActivePosition(activePosition - 1));
        }
        if (nextPageButton instanceof HTMLButtonElement) {
            nextPageButton.addEventListener('click', () => setActivePosition(activePosition + 1));
        }

        root.querySelector('[data-mol-zoom-in]')?.addEventListener('click', () => {
            const frame = frames[activePosition];
            setZoom(frame, zoomState(frame).scale + ZOOM_STEP);
        });
        root.querySelector('[data-mol-zoom-out]')?.addEventListener('click', () => {
            const frame = frames[activePosition];
            setZoom(frame, zoomState(frame).scale - ZOOM_STEP);
        });
        root.querySelector('[data-mol-zoom-reset]')?.addEventListener('click', () => {
            resetZoom(frames[activePosition]);
        });

        function pointerDistance(points) {
            const [first, second] = points;
            return Math.hypot(second.x - first.x, second.y - first.y);
        }

        for (const frame of frames) {
            const viewport = frame.querySelector('[data-mol-page-viewport]');
            if (!(viewport instanceof HTMLElement)) {
                continue;
            }
            const state = zoomState(frame);
            viewport.addEventListener('pointerdown', (event) => {
                if ('mouse' === event.pointerType && 0 !== event.button) {
                    return;
                }
                state.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                viewport.setPointerCapture?.(event.pointerId);
                if (2 === state.pointers.size) {
                    state.gesture = {
                        distance: pointerDistance(Array.from(state.pointers.values())),
                        scale: state.scale,
                        x: state.x,
                        y: state.y,
                    };
                } else if (1 === state.pointers.size) {
                    state.gesture = {
                        pointerX: event.clientX,
                        pointerY: event.clientY,
                        x: state.x,
                        y: state.y,
                    };
                }
            });
            viewport.addEventListener('pointermove', (event) => {
                if (!state.pointers.has(event.pointerId)) {
                    return;
                }
                state.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                if (state.pointers.size >= 2 && state.gesture?.distance > 0) {
                    event.preventDefault();
                    const distance = pointerDistance(Array.from(state.pointers.values()).slice(0, 2));
                    state.scale = clamp(state.gesture.scale * (distance / state.gesture.distance), MIN_ZOOM, MAX_ZOOM);
                    applyZoom(frame);
                } else if (1 === state.pointers.size && state.scale > MIN_ZOOM && state.gesture) {
                    event.preventDefault();
                    state.x = state.gesture.x + event.clientX - state.gesture.pointerX;
                    state.y = state.gesture.y + event.clientY - state.gesture.pointerY;
                    applyZoom(frame);
                }
            }, { passive: false });
            const endPointer = (event) => {
                state.pointers.delete(event.pointerId);
                if (1 === state.pointers.size) {
                    const remaining = Array.from(state.pointers.values())[0];
                    state.gesture = {
                        pointerX: remaining.x,
                        pointerY: remaining.y,
                        x: state.x,
                        y: state.y,
                    };
                } else if (0 === state.pointers.size) {
                    state.gesture = null;
                }
            };
            viewport.addEventListener('pointerup', endPointer);
            viewport.addEventListener('pointercancel', endPointer);
            viewport.addEventListener('wheel', (event) => {
                if (!event.ctrlKey && !event.metaKey) {
                    return;
                }
                event.preventDefault();
                setZoom(frame, state.scale + (event.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP));
            }, { passive: false });
            viewport.addEventListener('dblclick', () => resetZoom(frame));
        }

        document.addEventListener('keydown', (event) => {
            if ('paged' !== mode || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }
            const target = event.target;
            if (target instanceof Element
                && null !== target.closest('input, textarea, select, [contenteditable="true"]')
            ) {
                return;
            }
            const nextKey = 'rtl' === direction ? 'ArrowLeft' : 'ArrowRight';
            const previousKey = 'rtl' === direction ? 'ArrowRight' : 'ArrowLeft';
            if (event.key === nextKey) {
                event.preventDefault();
                setActivePosition(activePosition + 1);
            } else if (event.key === previousKey) {
                event.preventDefault();
                setActivePosition(activePosition - 1);
            }
        });

        function updateWebtoonPosition() {
            scrollFrame = 0;
            if ('webtoon' !== mode || restoringProgress) {
                return;
            }
            const readerLine = window.innerHeight * 0.45;
            let closestPosition = activePosition;
            let closestDistance = Infinity;
            frames.forEach((frame, index) => {
                const rect = frame.getBoundingClientRect();
                const distance = readerLine < rect.top
                    ? rect.top - readerLine
                    : readerLine > rect.bottom
                        ? readerLine - rect.bottom
                        : 0;
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestPosition = index;
                }
            });
            if (closestPosition !== activePosition) {
                activePosition = closestPosition;
                renderNear(activePosition);
                updatePageControls();
            }
            scheduleProgressSave();
        }

        window.addEventListener('scroll', () => {
            if (!scrollFrame) {
                scrollFrame = window.requestAnimationFrame(updateWebtoonPosition);
            }
        }, { passive: true });

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting && entry.target instanceof HTMLElement) {
                        renderFrame(entry.target);
                    }
                }
            }, { rootMargin: '100% 0px' });
            frames.forEach((frame) => observer.observe(frame));
        } else {
            frames.forEach((frame) => renderFrame(frame));
        }

        applyMode(mode, false);
        setActivePosition(activePosition, false);
        updateTranslation(translationVisible, false);

        window.requestAnimationFrame(() => {
            const savedUnit = integer(initialProgress?.progress_unit, 0, 0, MOL_UNIT);
            if ('webtoon' === mode && initialProgress) {
                const frame = frames[activePosition];
                frame.scrollIntoView({ block: 'start', behavior: 'auto' });
                const offset = (savedUnit / MOL_UNIT) * frame.getBoundingClientRect().height;
                window.scrollBy({ top: offset - (window.innerHeight * 0.45), behavior: 'auto' });
            }
            window.requestAnimationFrame(() => {
                restoringProgress = false;
                updatePageControls();
            });
        });

        window.addEventListener('pagehide', () => {
            if (scrollFrame) {
                window.cancelAnimationFrame(scrollFrame);
            }
            resizeObserver?.disconnect();
            void persistProgress({ silent: true, keepalive: true });
        });
    }

    const api = {
        MOL_UNIT,
        asPercentage,
        clamp,
        createProgressPayload,
        normalizeDirection,
        normalizeMode,
        normalizeStyle,
        progressStorageKey,
    };

    if ('undefined' !== typeof module && module.exports) {
        module.exports = api;
    }
    if ('undefined' !== typeof window) {
        window.MOLReaderCore = api;
        if ('loading' === document.readyState) {
            document.addEventListener('DOMContentLoaded', initReader, { once: true });
        } else {
            initReader();
        }
    }
})();
