$(() => {
    $('[data-role="run-collection-analysis"]').on('click', function () {
        const $btn = $(this);
        const url = $btn.data('url');

        $btn.prop('disabled', true).text('Analizowanie...');

        $.post(url, () => {
            $btn.text('Analiza uruchomiona');
        });
    });

    $(document).on('click', '[data-role="toggle-ignore"]', function () {
        const $btn = $(this);
        const url = $btn.data('url');
        const hash = $btn.data('hash');

        $.post(url, { hash: hash }, function (data) {
            const $container = $btn.closest('[data-element="analysis-issue"]');
            const $iconAccept = $btn.find('[data-element="icon-accept"]');
            const $iconRestore = $btn.find('[data-element="icon-restore"]');

            $container.toggleClass('text-muted ast-status-ignored', data.ignored);
            $btn.attr('title', data.ignored ? 'Przywróć' : 'Akceptuj');
            $iconAccept.toggleClass('d-none', data.ignored);
            $iconRestore.toggleClass('d-none', !data.ignored);
        });
    });

    $(document).on('click', '[data-role="show-raw-data"]', function () {
        var raw = $(this).data('raw');
        $('#rawDataContent').text(JSON.stringify(raw, null, 2));
        new bootstrap.Modal('#rawDataModal').show();
    });

    highlightDiffs();
});

function lcs(a, b) {
    const m = a.length;
    const n = b.length;
    const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));

    for (let i = 1; i <= m; i++) {
        for (let j = 1; j <= n; j++) {
            dp[i][j] = a[i - 1] === b[j - 1]
                ? dp[i - 1][j - 1] + 1
                : Math.max(dp[i - 1][j], dp[i][j - 1]);
        }
    }

    const result = [];
    let i = m, j = n;

    while (i > 0 && j > 0) {
        if (a[i - 1] === b[j - 1]) {
            result.unshift({ ch: a[i - 1], ai: i - 1, bi: j - 1 });
            i--;
            j--;
        } else if (dp[i - 1][j] > dp[i][j - 1]) {
            i--;
        } else {
            j--;
        }
    }

    return result;
}

function buildDiffHtml(str, common, indexKey, diffClass, sameClass) {
    const matched = new Set(common.map(entry => entry[indexKey]));

    const escape = ch => ch
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    let html = '';
    let runSame = null;
    let runText = '';

    const flush = () => {
        if (!runText) return;
        html += `<span class="${runSame ? sameClass : diffClass}">${runText}</span>`;
        runText = '';
    };

    for (let i = 0; i < str.length; i++) {
        const isSame = matched.has(i);

        if (runSame === null || isSame !== runSame) {
            flush();
            runSame = isSame;
        }
        runText += escape(str[i]);
    }
    flush();

    return html;
}

function highlightDiffs() {
    const $aElements = $('[data-diff-a]');
    const $bElements = $('[data-diff-b]');
    const n = Math.min($aElements.length, $bElements.length);

    for (let i = 0; i < n; i++) {
        const $a = $aElements.eq(i);
        const $b = $bElements.eq(i);
        const aText = `${$a.attr('data-diff-a')}`;
        const bText = `${$b.attr('data-diff-b')}`;
        const common = lcs(aText, bText);
        const classA = $a.attr('data-diff-class') || 'ast-diff-diff';
        const classB = $b.attr('data-diff-class') || 'ast-diff-diff';

        $a.html(buildDiffHtml(aText, common, 'ai', classA, 'ast-diff-same'));
        $b.html(buildDiffHtml(bText, common, 'bi', classB, 'ast-diff-same'));
    }
}
