// Funkcja do pobierania średniej wartości z okna próbek
const getAverageValue = (channels, centerIndex, windowSize = 5) => {
    const halfWindow = Math.floor(windowSize / 2)

    let sum = 0
    let count = 0

    for (let j = centerIndex - halfWindow; j <= centerIndex + halfWindow; j++) {
        if (j >= 0 && j < channels[0].length) {
            sum += Math.abs(channels[0][j])
            count++
        }
    }

    return count > 0 ? sum / count : 0
}

// Funkcja do wygładzania między sąsiednimi słupkami
const smoothValues = (values, smoothingFactor = 0.3) => {
    const smoothed = [...values]

    for (let i = 1; i < values.length - 1; i++) {
        const prev = values[i - 1]
        const current = values[i]
        const next = values[i + 1]

        // Średnia ważona: aktualna wartość ma większą wagę, ale sąsiedzi również wpływają
        smoothed[i] = current * (1 - smoothingFactor) +
            (prev + next) * smoothingFactor / 2
    }

    return smoothed
}

// Funkcja do miękkiego skalowania wartości y - zachowuje różnice między wysokimi słupkami
const softScaleY = (baseline, height, y, isUpperWave) => {
    const margin = 5 // margines od krawędzi canvas

    if (isUpperWave) {
        const maxY = margin
        const minY = baseline - margin

        if (y >= minY) {
            return y // normalne wartości pozostają bez zmian
        }

        // Dla wartości przekraczających granice stosujemy miękkie skalowanie
        const excess = minY - y
        const availableSpace = minY - maxY

        // Logarytmiczne "ściskanie" - zachowuje różnice ale mieści w granicach
        const scaledExcess = availableSpace * (1 - Math.exp(-excess / availableSpace * 2))
        return minY - scaledExcess

    } else {
        const minY = baseline + margin
        const maxY = height - margin

        if (y <= maxY) {
            return y // normalne wartości pozostają bez zmian
        }

        // Dla wartości przekraczających granice stosujemy miękkie skalowanie  
        const excess = y - maxY
        const availableSpace = maxY - minY

        // Logarytmiczne "ściskanie" - zachowuje różnice ale mieści w granicach
        const scalingFactor = 1 - Math.exp(-excess / availableSpace * 2)
        const scaledExcess = availableSpace * scalingFactor
        return minY + scaledExcess // ściśnięte wartości w dostępnej przestrzeni
    }
}

const upperWaveColor = '#666'
const lowerWaveColor = '#999'

/**
 * Render a waveform as a squiggly line with smoothing and better dynamics
 * @see https://css-tricks.com/making-an-audio-waveform-visualizer-with-vanilla-javascript/
 */
export default function (channels, ctx) {
    const { width, height } = ctx.canvas
    const scale = channels[0].length / width
    const step = 9
    const baseline = height / 1.3
    const scaleFactor = 0.9

    // Zbieramy wszystkie wartości dla obu fal
    const rawValues = []
    const positions = []

    for (let i = 0; i < width; i += step * 2) {
        const index = Math.floor(i * scale)
        const value = getAverageValue(channels, index, 8) // używamy średniej z 8 próbek
        rawValues.push(value)
        positions.push(i)
    }

    // Wygładzamy wartości między sąsiednimi słupkami
    const smoothedValues = smoothValues(rawValues, 0.25)

    // Normalizacja: zwiększamy dynamikę ale zachowujemy proporcje
    const maxValue = Math.max(...smoothedValues)
    const amplification = maxValue > 0 ? Math.min(2.2, 1.0 / maxValue + 0.8) : 1.0

    ctx.clearRect(0, 0, width, height)

    //
    // 1. GÓRNA FALA (normalna)
    //
    ctx.strokeStyle = upperWaveColor
    ctx.beginPath()

    for (let i = 0; i < smoothedValues.length; i++) {
        const value = smoothedValues[i] * amplification
        const x = positions[i]
        let y = baseline - value * height * scaleFactor
        y = softScaleY(baseline, height, y, true) // miękkie skalowanie zachowujące różnice

        ctx.moveTo(x, baseline)
        ctx.lineTo(x, y)
        ctx.arc(x + step / 2, y, step / 2, Math.PI, 0, false)
        ctx.lineTo(x + step, baseline)

        ctx.lineTo(x + step * 2, baseline)
    }

    ctx.stroke()
    ctx.closePath()

    //
    // 2. DOLNA FALA (jaśniejsza)
    //
    ctx.strokeStyle = lowerWaveColor
    ctx.beginPath()

    const offset = 8
    const scaleDown = 0.35

    for (let i = 0; i < smoothedValues.length; i++) {
        const value = smoothedValues[i] * amplification * scaleDown
        const x = positions[i]
        let y = baseline + offset + value * height * scaleFactor
        y = softScaleY(baseline, height, y, false) // miękkie skalowanie dla dolnej fali

        ctx.moveTo(x, baseline + offset)
        ctx.lineTo(x, y)
        ctx.arc(x + step / 2, y, step / 2, Math.PI, 0, true)
        ctx.lineTo(x + step, baseline + offset)

        ctx.lineTo(x + step * 2, baseline + offset)
    }

    ctx.stroke()
    ctx.closePath()
}
