const esbuild = require('esbuild');
const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

const BUILD_COOLDOWN_MS = 15 * 60 * 1000;
const IS_WATCH = process.argv.includes('--watch');
const IS_PRODUCTION = process.argv.includes('--production');

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
    SUCCESS: '✅',
    WATCH: '👀',
};

const getCurrentDate = () => new Date().toISOString();

const getFileSize = filePath => {
    try {
        const { size } = fs.statSync(filePath);
        return Math.round((size / 1024) * 100) / 100;
    } catch {
        return 0;
    }
};

const createPublicAliasPlugin = () => ({
    name: 'public-alias',
    setup(build) {
        build.onResolve({ filter: /^@public\// }, args => ({
            path: path.resolve(__dirname, 'public', args.path.replace(/^@public\//, ''))
        }));
    }
});

const createBuildConfig = ({ buildTime = 0, fileSize = 0, mode = BuildMode.DEV }) => {
    const isProduction = mode === BuildMode.PROD;

    const js = `
        window.assistant = {
          mix: {
            buildInfo: {
              buildDate: "${getCurrentDate()}",
              buildTimeMs: ${buildTime},
              buildSizeKb: ${fileSize},
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
        outfile: 'public/js/mix.dist.js',
        platform: 'browser',
        plugins: [ createPublicAliasPlugin() ],
        sourcemap: !isProduction,
    };
};

const scheduleProductionBuild = (state, buildFn) => {
    if (state.prodTimer) {
        clearTimeout(state.prodTimer);
    }

    state.prodTimer = setTimeout(async () => {
        try {
            log(LogType.AUTO_BUILD, 'Running prod build...');

            const result = await buildFn(BuildMode.PROD);

            log(LogType.COMPLETE, `Auto prod build complete (${result.buildTime}ms, ${result.fileSize}KB)`);
        } catch (error) {
            log(LogType.ERROR, 'Auto prod build error', error);
        }
    }, BUILD_COOLDOWN_MS);
};

const executeBuild = async (mode) => {
    const startTime = Date.now();

    await esbuild.build(createBuildConfig({ mode }));

    const buildTime = Date.now() - startTime;
    const fileSize = getFileSize('public/js/mix.dist.js');

    await esbuild.build(createBuildConfig({ buildTime, fileSize, mode }));

    return { buildTime, fileSize };
};

const runBuild = async (mode, state) => {
    log(LogType.BUILD, `Building [${mode}]...`);

    const result = await executeBuild(mode);

    if (mode === BuildMode.DEV && state) {
        state.lastDevBuildAt = Date.now();
    }

    log(LogType.COMPLETE, `Build complete [${mode}] (${result.buildTime}ms, ${result.fileSize}KB)`);

    return result;
};

const handleFileChange = async (changedPath, state) => {
    state.lastChangeAt = Date.now();

    log(LogType.CHANGE, changedPath);

    try {
        const result = await runBuild(BuildMode.DEV, state);

        log(LogType.SUCCESS, `Build [dev] (${result.buildTime}ms, ${result.fileSize}KB)`);

        scheduleProductionBuild(state, executeBuild);
    } catch (error) {
        log(LogType.ERROR, 'Build error', error);
    }
};

const startWatchMode = async (state) => {
    log(LogType.WATCH, 'Watch mode enabled');

    const initialResult = await runBuild(BuildMode.DEV, state);
    state.lastChangeAt = Date.now();

    log(LogType.SUCCESS, `Initial build [dev] (${initialResult.buildTime}ms, ${initialResult.fileSize}KB)`);

    scheduleProductionBuild(state, executeBuild);

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

const runSingleBuild = async () => {
    const mode = IS_PRODUCTION ? BuildMode.PROD : BuildMode.DEV;

    await runBuild(mode);
};

const formatTimestamp = () => {
    const format = '2-digit';

    return new Intl.DateTimeFormat('pl-PL', {
        day: format,
        month: format,
        hour: format,
        minute: format,
        second: format,
        fractionalSecondDigits: 3,
    })
    .format(new Date())
    .replace(',', '.');
};

const log = (type, message, data = undefined) => {
    const prefix = `[${formatTimestamp()}] ${type}`;
    const payload = data === undefined ? message : `${message} | Details:`;

    console.log(prefix, payload, ...(data === undefined ? [] : [ data ]));
};

const main = async () => {
    const state = ({
        lastChangeAt: 0,
        lastDevBuildAt: 0,
        prodTimer: null
    });

    try {
        if (IS_WATCH) {
            await startWatchMode(state);

            return;
        }

        await runSingleBuild();
    } catch (error) {
        log(LogType.ERROR, 'Fatal build error', error);

        process.exit(1);
    }
};

main().then(_ => true);
