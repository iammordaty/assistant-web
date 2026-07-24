const esbuild = require('esbuild');
const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

const BUILD_COOLDOWN_MS = 15 * 60 * 1000;

const OUTFILE = 'public/js/mix.dist.js';

// Injected verbatim into the banner, then patched with real values once the
// build has run (buildTime/fileSize are unknown until then). esbuild does not
// parse the banner, so a bare identifier here is fine until it is replaced.
const BUILD_TIME_PLACEHOLDER = '__ASSISTANT_BUILD_TIME_MS__';
const BUILD_SIZE_PLACEHOLDER = '__ASSISTANT_BUILD_SIZE_KB__';

const IS_WATCH = process.argv.includes('--watch');
const IS_PRODUCTION = process.argv.includes('--production');

// refactor:
// https://www.kimi.com/chat/19f8bc1f-b662-8a25-8000-093cebeb8c58
// https://chat.deepseek.com/a/chat/s/8ec5ffcb-b047-43b1-adfd-85954054dca8

const BuildMode = {
    PROD: 'prod',
    DEV: 'dev'
};

const LogType = {
    AUTO_BUILD: '⏱️',
    BUILD: '🏗️',
    CHANGE: '🔧',
    COMPLETE: '📦',
    ERROR: '❌',
    INFO: 'ℹ️',
    WATCH: '👀',
};

const VENDOR_ASSETS = [
    { from: '@tabler/core/dist/css/tabler.min.css', to: 'public/vendor/tabler.min.css' },
    { from: '@tabler/core/dist/css/tabler-vendors.min.css', to: 'public/vendor/tabler-vendors.min.css' },
    { from: '@tabler/core/dist/js/tabler.min.js', to: 'public/vendor/tabler.min.js' },
    { from: 'jquery/dist/jquery.min.js', to: 'public/vendor/jquery-3.7.0.min.js' },
    { from: 'tablesorter/dist/js/jquery.tablesorter.min.js', to: 'public/vendor/jquery.tablesorter.min.js' },
    { from: '@popperjs/core/dist/umd/popper.min.js', to: 'public/vendor/popper.min.js' },
    { from: 'wavesurfer.js/dist/wavesurfer.min.js', to: 'public/vendor/wavesurfer.min.js' },
];

const log = (type, message, data = undefined) => {
    const timestamp = new Intl.DateTimeFormat('pl-PL', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        fractionalSecondDigits: 3,
    })
    .format(new Date());

    const prefix = `[${timestamp}] ${type}`;
    const payload = data === undefined ? message : `${message} | Details:`;

    console.log(prefix, payload, ...(data === undefined ? [] : [ data ]));
};

const getFileSize = filePath => {
    try {
        const { size } = fs.statSync(filePath);

        return Math.round((size / 1024) * 100) / 100;
    } catch {
        return 0;
    }
};

const copyVendorAssets = () => {
    VENDOR_ASSETS.forEach(({ from, to }) => {
        const source = path.resolve(__dirname, 'node_modules', from);
        const target = path.resolve(__dirname, to);

        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.copyFileSync(source, target);
    });
};

const createPublicAliasPlugin = () => ({
    name: 'public-alias',
    setup(build) {
        build.onResolve({ filter: /^@public\// }, args => ({
            path: path.resolve(__dirname, 'public', args.path.replace(/^@public\//, ''))
        }));
    }
});

const createBuildConfig = (mode) => {
    const isProduction = mode === BuildMode.PROD;
    const buildDate = new Date().toISOString();

    const js = `
        window.assistant = {
          mix: {
            buildInfo: {
              buildDate: "${buildDate}",
              buildTimeMs: ${BUILD_TIME_PLACEHOLDER},
              buildSizeKb: ${BUILD_SIZE_PLACEHOLDER},
              buildMode: "${mode}"
            }
          }
        };
    `;

    return {
        banner: { js },
        bundle: true,
        entryPoints: [ 'src/Assistant/Module/Mix/Resources/js/index.jsx' ],
        jsx: 'automatic',
        minify: isProduction,
        outfile: OUTFILE,
        platform: 'browser',
        plugins: [ createPublicAliasPlugin() ],
        sourcemap: !isProduction,
    };
};

const scheduleProductionBuild = (state) => {
    clearTimeout(state.prodTimer);

    state.prodTimer = setTimeout(async () => {
        try {
            log(LogType.AUTO_BUILD, 'Cooldown elapsed, running prod build...');

            await runBuild(BuildMode.PROD);
        } catch (error) {
            log(LogType.ERROR, 'Auto prod build error', error);
        }
    }, BUILD_COOLDOWN_MS);
};

const injectBuildInfo = ({ buildTime, fileSize }) => {
    const content = fs
        .readFileSync(OUTFILE, 'utf8')
        .replace(BUILD_TIME_PLACEHOLDER, String(buildTime))
        .replace(BUILD_SIZE_PLACEHOLDER, String(fileSize));

    fs.writeFileSync(OUTFILE, content);
};

const executeBuild = async (mode) => {
    const startTime = Date.now();

    copyVendorAssets();

    await esbuild.build(createBuildConfig(mode));

    const buildTime = Date.now() - startTime;
    const fileSize = getFileSize(OUTFILE);

    injectBuildInfo({ buildTime, fileSize });

    return { buildTime, fileSize };
};

const runBuild = async (mode) => {
    log(LogType.BUILD, `Building (${mode})...`);

    const result = await executeBuild(mode);

    log(LogType.COMPLETE, `Build complete (${mode}) (${result.buildTime}ms, ${result.fileSize}KB)`);

    return result;
};

const handleFileChange = async (changedPath, state) => {
    log(LogType.CHANGE, changedPath);

    try {
        await runBuild(BuildMode.DEV);

        scheduleProductionBuild(state);
    } catch (error) {
        log(LogType.ERROR, 'Build error', error);
    }
};

const startWatchMode = async (state) => {
    log(LogType.WATCH, 'Watch mode enabled');

    await runBuild(BuildMode.DEV);

    scheduleProductionBuild(state);

    const watchPaths = [
        'src/Assistant/Module/Mix/Resources/js',
        'public/js/modules'
    ];

    const ignoredPaths = [
        'public/js/mix.dist.js',
        '**/@eaDir',
        '**/@eaDir/**',
        '**/*@SynoEAStream'
    ];

    const watcher = chokidar.watch(watchPaths, {
        ignored: ignoredPaths,
        ignoreInitial: true,
        persistent: true
    });

    watcher.on('ready', () => log(LogType.INFO, 'Watching for changes...'));

    watcher.on('change', (changedPath) => handleFileChange(changedPath, state));
};

const main = async () => {
    try {
        if (IS_WATCH) {
            await startWatchMode({ prodTimer: null });

            return;
        }

        await runBuild(IS_PRODUCTION ? BuildMode.PROD : BuildMode.DEV);
    } catch (error) {
        log(LogType.ERROR, 'Fatal build error', error);

        process.exit(1);
    }
};

main();
