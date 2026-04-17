$(function () {
    $('[data-role="run-collection-analysis"]').on('click', function () {
        var $btn = $(this);

        $btn.prop('disabled', true).text('Analizowanie...');

        $.post('/common/task/run-collection-analysis', function () {
            $btn.text('Analiza uruchomiona');
        });
    });

    highlightFilenameDiffs();
    highlightSimilarityDiffs();
});

function lcs(a, b) {
    var m = a.length;
    var n = b.length;
    var dp = [];

    for (var i = 0; i <= m; i++) {
        dp[i] = [];
        for (var j = 0; j <= n; j++) {
            if (i === 0 || j === 0) {
                dp[i][j] = 0;
            } else if (a[i - 1] === b[j - 1]) {
                dp[i][j] = dp[i - 1][j - 1] + 1;
            } else {
                dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
            }
        }
    }

    var result = [];
    var i = m, j = n;

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

function buildDiffHtml(str, common, indexKey, delClass, sameClass) {
    var matched = {};

    for (var k = 0; k < common.length; k++) {
        matched[common[k][indexKey]] = true;
    }

    var html = '';
    var runSame = null;
    var runText = '';

    function escape(ch) {
        return ch.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function flush() {
        if (runText === '') {
            return;
        }
        html += '<span class="' + (runSame ? sameClass : delClass) + '">' + runText + '</span>';
        runText = '';
    }

    for (var i = 0; i < str.length; i++) {
        var isSame = !!matched[i];

        if (runSame === null || isSame !== runSame) {
            flush();
            runSame = isSame;
        }
        runText += escape(str[i]);
    }
    flush();

    return html;
}

function highlightFilenameDiffs() {
    $('[data-diff-actual]').each(function () {
        var $actual = $(this);
        var $expected = $actual.closest('.ast-fmatch-diff').find('[data-diff-expected]');

        if (!$expected.length) {
            return;
        }

        var a = $actual.data('diff-actual') + '';
        var b = $expected.data('diff-expected') + '';
        var common = lcs(a, b);

        $actual.html(buildDiffHtml(a, common, 'ai', 'ast-diff-ch-del', 'ast-diff-ch-same'));
        $expected.html(buildDiffHtml(b, common, 'bi', 'ast-diff-ch-ins', 'ast-diff-ch-same'));
    });
}

function highlightSimilarityDiffs() {
    $('[data-sim-value-a]').each(function () {
        var $a = $(this);
        var $b = $a.closest('.ast-sim-header').find('[data-sim-value-b]');

        if (!$b.length) {
            return;
        }

        var a = $a.data('sim-value-a') + '';
        var b = $b.data('sim-value-b') + '';
        var common = lcs(a, b);

        $a.html(buildDiffHtml(a, common, 'ai', 'ast-diff-seg-diff', 'ast-diff-seg-same'));
        $b.html(buildDiffHtml(b, common, 'bi', 'ast-diff-seg-diff', 'ast-diff-seg-same'));
    });
}
