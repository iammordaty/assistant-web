/* global $ */

export default time => time.split(':').reduce((acc, time) => (60 * acc) + +time)
