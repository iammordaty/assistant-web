export default function (container) {
    const initialized = new WeakSet();

    let observer = null;

    function init(selector, initializer) {
        initExisting(selector, initializer);

        observe(selector, initializer);
    }

    function initExisting(selector, initializer) {
        container
            .querySelectorAll(selector)
            .forEach(el => initialize(el, initializer));
    }

    function observe(selector, initializer) {
        observer ??= new MutationObserver(mutations => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (!(node instanceof HTMLElement)) {
                        continue;
                    }

                    tryInit(node, selector, initializer);
                }
            }
        });

        observer.observe(container, {
            childList: true,
            subtree: true,
        });
    }

    function tryInit(node, selector, initializer) {
        if (node.matches(selector)) {
            initialize(node, initializer);
        }

        node
            .querySelectorAll?.(selector)
            .forEach(el => initialize(el, initializer));
    }

    function initialize(element, initializer) {
        if (initialized.has(element)) {
            return;
        }

        initializer(element);

        initialized.add(element);
    }

    return { init };
}
