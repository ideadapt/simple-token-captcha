/**
 * Show stored server result in '[data-simple-captcha-result]' element.
 *
 * @param texts
 */
export function renderCaptchaServerResult(texts) {
    const result = window.localStorage.getItem('simple-captcha-result')
    const lang = window.localStorage.getItem('simple-captcha-lang')
    if (result != null) {
        window.localStorage.removeItem('simple-captcha-result')

        document.querySelectorAll('[data-simple-captcha-result]').forEach(resultEl => {
            resultEl.innerText = texts[lang][result]
        })
    }
}

/**
 * Default translations for texts used in default functions.
 */
export const texts = {
    'fr': {
        'wrong-answer': 'Réponse incorrecte. Essayez à nouveau.',
        'validation-failed': 'Quelque chose s\'est mal passé. Veuillez réessayer.',
        'captcha-instruction': 'Veuillez répondre à la question suivante. Cela garantit que vous n’êtes pas une machine :).',
        'cancel': 'Annuler',
        'confirm': 'Envoye!',
        'title': 'Requête de sécurité',
        'placeholder': 'Répondre',
    },
    'de': {
        'wrong-answer': 'Antwort nicht korrekt. Bitte erneut versuchen.',
        'validation-failed': 'Etwas ist schief gelaufen. Bitte erneut versuchen.',
        'captcha-instruction': 'Beantworte bitte folgende Frage. Damit stellen wir sicher, dass du keine Maschine bist :).',
        'cancel': 'Abbrechen',
        'confirm': 'Absenden!',
        'title': 'Sicherheitsabfrage',
        'placeholder': 'Antwort'
    }
}

/**
 * Submit given form
 *
 * @param form
 */
export function onValidationOk(form) {
    // since we use form.requestSubmit(), the submit input field is not included in the form data, but hidden fields are.
    // thus, clone submit input field and create a corresponding hidden input.
    const submit = form.querySelector('[type="submit"]')
    if (submit != null) {
        const {name, value} = submit
        const hiddenSubmit = document.createElement('input')
        hiddenSubmit.setAttribute('type', 'hidden')
        hiddenSubmit.setAttribute('name', name)
        hiddenSubmit.setAttribute('value', value)
        form.appendChild(hiddenSubmit)
    }

    form.requestSubmit()
}

/**
 * Store server result and reload page.
 * @param result
 * @param lang
 */
export function onValidationFailed(result, lang) {
    window.localStorage.setItem('simple-captcha-lang', lang)
    window.localStorage.setItem('simple-captcha-result', result)
    document.location.reload()
}

/**
 *
 * @param challenge
 * @param dialogId
 * @param texts
 * @param lang
 * @returns {string} either 'cancelled' or 'confirmed'
 */
export function dialogHtml(challenge, dialogId, texts, lang){
    return `
            <dialog id="${dialogId}">
            <form method="dialog" autocomplete="off">
              <h1>${texts[lang]['title']}</h1>
              <p><small>${texts[lang]['captcha-instruction']}</small></p>
              <br/>
              <p style="font-size: 1.1em">${challenge}</p>
              <br/>
              <input type="text" autofocus id="simple-captcha-dialog-response" placeholder="${texts[lang]['placeholder']}">
              <p>
                  <button value="confirmed">${texts[lang]['confirm']}</button>
                  <button value="cancelled">${texts[lang]['cancel']}</button>
              </p>
            </form>
            </dialog>
            `
}
