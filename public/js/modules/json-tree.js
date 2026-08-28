const ARRAY_PAGE_SIZE = 100;

export default function renderJsonTree(data) {
    const root = document.createElement('div');
    root.className = 'ast-json-tree';

    if (data !== null && typeof data === 'object') {
        appendEntries(root, data, 0);
    } else {
        root.append(renderLeaf(data));
    }

    return root;
}

function appendEntries(container, value, depth) {
    if (Array.isArray(value)) {
        appendArrayItems(container, value, depth);

        return;
    }

    for (const [key, child] of Object.entries(value)) {
        container.append(renderEntry(key, child, depth));
    }
}

function appendArrayItems(container, items, depth, offset = 0) {
    const end = Math.min(offset + ARRAY_PAGE_SIZE, items.length);

    for (let i = offset; i < end; i++) {
        container.append(renderEntry(String(i), items[i], depth));
    }

    if (end >= items.length) {
        return;
    }

    const remaining = items.length - end;
    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'btn btn-sm mt-1 mb-1';
    more.textContent = remaining === 1
        ? 'Pokaż pozostały element'
        : `Pokaż kolejne (${remaining})`;

    more.addEventListener('click', () => {
        more.remove();
        appendArrayItems(container, items, depth, end);
    });

    container.append(more);
}

function renderEntry(key, value, depth) {
    const isCollection = value !== null && typeof value === 'object';

    if (!isCollection) {
        const row = document.createElement('div');
        row.className = 'ast-json-row';
        row.append(renderKey(key), document.createTextNode(': '), renderLeaf(value));

        return row;
    }

    const details = document.createElement('details');
    details.className = 'ast-json-collection';

    const summary = document.createElement('summary');
    summary.append(renderKey(key), document.createTextNode(': '), renderPreview(value));
    details.append(summary);

    details.addEventListener('toggle', () => {
        if (!details.open || details.dataset.rendered) {
            return;
        }

        details.dataset.rendered = '1';

        const children = document.createElement('div');
        children.className = 'ast-json-children';
        appendEntries(children, value, depth + 1);
        details.append(children);
    });

    return details;
}

function renderKey(key) {
    const el = document.createElement('span');
    el.className = 'ast-json-key';
    el.textContent = key;

    return el;
}

function renderPreview(value) {
    const el = document.createElement('span');
    el.className = 'ast-json-preview';

    if (Array.isArray(value)) {
        el.textContent = `[${value.length}]`;
    } else {
        el.textContent = `{${Object.keys(value).length}}`;
    }

    return el;
}

function renderLeaf(value) {
    const el = document.createElement('span');

    if (value === null) {
        el.className = 'ast-json-null';
        el.textContent = 'null';
    } else if (typeof value === 'string') {
        el.className = 'ast-json-string';
        el.textContent = JSON.stringify(value);
    } else if (typeof value === 'number') {
        el.className = 'ast-json-number';
        el.textContent = String(value);
    } else if (typeof value === 'boolean') {
        el.className = 'ast-json-boolean';
        el.textContent = String(value);
    } else {
        el.textContent = String(value);
    }

    return el;
}
