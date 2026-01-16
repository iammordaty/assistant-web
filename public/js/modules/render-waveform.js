'use strict';

const CONFIG = {
    upperWaveColor: '#666',
    lowerWaveColor: '#999',

    barStep: 9,                    // szerokość pojedynczego słupka
    baselineDivisor: 1.3,          // dzielnik wysokości dla linii bazowej
    canvasMargin: 5,               // margines od krawędzi canvas
    lowerWaveOffset: 8,            // odstęp dolnej fali od linii bazowej
    lowerWaveScale: 0.35,          // skala wysokości dolnej fali względem górnej

    averagingWindowSize: 8,        // liczba próbek do uśrednienia
    smoothingFactor: 0.15,         // współczynnik wygładzania (0-1)
    amplitudeScaleFactor: 0.9,     // mnożnik amplitudy

    maxAmplification: 2.2,         // maksymalne wzmocnienie
    amplificationBase: 0.8,        // bazowy offset wzmocnienia

    compressionStrength: 2.5,      // siła kompresji w formule eksponencjalnej
}

// Funkcja do pobierania średniej wartości z okna próbek (retrospektywne, aby uniknąć opóźnienia wizualnego)
const getAverageValue = (channels, centerIndex, windowSize) => {
    let sum = 0
    let count = 0

    // Używamy próbek od (centerIndex - windowSize + 1) do centerIndex (włącznie)
    // To eliminuje "zaglądanie w przyszłość" i synchronizuje piki z atakami dźwięku
    for (let j = centerIndex - windowSize + 1; j <= centerIndex; j++) {
        if (j >= 0 && j < channels[0].length) {
            sum += Math.abs(channels[0][j])
            count++
        }
    }

    return count > 0 ? sum / count : 0
}

// Funkcja do wygładzania między sąsiednimi słupkami (retrospektywne, aby uniknąć opóźnienia)
const smoothValues = (values, smoothingFactor) => {
    const smoothed = [...values]

    for (let i = 1; i < values.length; i++) {
        const prev = values[i - 1]
        const current = values[i]

        // Wygładzanie tylko z poprzednim słupkiem - eliminuje opóźnienie wizualne
        smoothed[i] = current * (1 - smoothingFactor) + prev * smoothingFactor
    }

    return smoothed
}

// Funkcja do miękkiego skalowania wartości y - zachowuje różnice między wysokimi słupkami
const softScaleY = (baseline, height, y, isUpperWave) => {
    const { canvasMargin, compressionStrength } = CONFIG

    if (isUpperWave) {
        const topBoundary = canvasMargin
        const bottomBoundary = baseline - canvasMargin

        if (y >= bottomBoundary) {
            return y // normalne wartości pozostają bez zmian
        }

        // Dla wartości przekraczających granice stosujemy miękkie skalowanie
        const excess = bottomBoundary - y
        const availableSpace = bottomBoundary - topBoundary

        // Logarytmiczne "ściskanie" - zachowuje różnice ale mieści w granicach
        const scaledExcess = availableSpace * (1 - Math.exp(-excess / availableSpace * compressionStrength))

        return bottomBoundary - scaledExcess
    } else {
        const topBoundary = baseline + canvasMargin
        const bottomBoundary = height - canvasMargin

        if (y <= bottomBoundary) {
            return y // normalne wartości pozostają bez zmian
        }

        // Dla wartości przekraczających granice stosujemy miękkie skalowanie  
        const excess = y - bottomBoundary
        const availableSpace = bottomBoundary - topBoundary

        // Logarytmiczne "ściskanie" - zachowuje różnice ale mieści w granicach
        const scalingFactor = 1 - Math.exp(-excess / availableSpace * compressionStrength)
        const scaledExcess = availableSpace * scalingFactor
        
        return topBoundary + scaledExcess // ściśnięte wartości w dostępnej przestrzeni
    }
}

/**
 * Render a waveform as a squiggly line with smoothing and better dynamics
 * @see https://css-tricks.com/making-an-audio-waveform-visualizer-with-vanilla-javascript/
 */
export default function (channels, ctx) {
    const { width, height } = ctx.canvas
    const {
        barStep,
        baselineDivisor,
        upperWaveColor,
        lowerWaveColor,
        lowerWaveOffset,
        lowerWaveScale,
        averagingWindowSize,
        smoothingFactor,
        amplitudeScaleFactor,
        maxAmplification,
        amplificationBase,
    } = CONFIG

    const scale = channels[0].length / width
    const baseline = height / baselineDivisor
    const lowerWaveStart = baseline + lowerWaveOffset

    const rawValues = []
    const positions = []

    for (let i = 0; i < width; i += barStep * 2) {
        const index = Math.floor(i * scale)
        const value = getAverageValue(channels, index, averagingWindowSize)
        rawValues.push(value)
        positions.push(i)
    }

    // Wygładzamy wartości między sąsiednimi słupkami
    const smoothedValues = smoothValues(rawValues, smoothingFactor)

    // Normalizacja: zwiększamy dynamikę ale zachowujemy proporcje
    const maxValue = Math.max(...smoothedValues)
    const amplification = maxValue > 0 ? Math.min(maxAmplification, 1.0 / maxValue + amplificationBase) : 1.0

    ctx.clearRect(0, 0, width, height)

    // Rysowanie górnej fali

    ctx.strokeStyle = upperWaveColor
    ctx.beginPath()

    for (let i = 0; i < smoothedValues.length; i++) {
        const value = smoothedValues[i] * amplification
        const x = positions[i]
        let y = baseline - value * height * amplitudeScaleFactor
        y = softScaleY(baseline, height, y, true)

        ctx.moveTo(x, baseline)
        ctx.lineTo(x, y)
        ctx.arc(x + barStep / 2, y, barStep / 2, Math.PI, 0, false)
        ctx.lineTo(x + barStep, baseline)

        ctx.lineTo(x + barStep * 2, baseline)
    }

    ctx.stroke()
    ctx.closePath()

    // Rysowanie dolnej fali
    
    ctx.strokeStyle = lowerWaveColor
    ctx.beginPath()

    for (let i = 0; i < smoothedValues.length; i++) {
        const value = smoothedValues[i] * amplification * lowerWaveScale
        const x = positions[i]
        let y = lowerWaveStart + value * height * amplitudeScaleFactor
        y = softScaleY(baseline, height, y, false)

        ctx.moveTo(x, lowerWaveStart)
        ctx.lineTo(x, y)
        ctx.arc(x + barStep / 2, y, barStep / 2, Math.PI, 0, true)
        ctx.lineTo(x + barStep, lowerWaveStart)

        ctx.lineTo(x + barStep * 2, lowerWaveStart)
    }

    ctx.stroke()
    ctx.closePath()
}
