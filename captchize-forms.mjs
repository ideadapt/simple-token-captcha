/**
 * Add same captcha behaviour to all forms that support captchas on the current page.
 * Each form will have the same challenge.
 * A form supports captcha if it has these two input fields:
 *  [name="simple-captcha-token"] - hidden input field, which stores the server generated token
 *  [name="simple-captcha-response"] - hidden input field, which will contain the user provided answer
 *
 * When using the default validation functions,
 *  add a [data-simple-captcha-result] element within the form to render a translated server validation result message.
 *  E.g.: <p data-simple-captcha-result></p>
 */
export function captchizeForms({
                                   dialogId,
                                   texts,
                                   /**
                                    * Called on every page load. Use it to render a client side stored message.
                                    */
                                   renderCaptchaServerResult,
                                   onValidationOk,
                                   onValidationFailed,
                                   dialogHtml
                               }) {

    function createCaptchaConfirmDialog(challenge, lang, dialogId) {
        const dialog = dialogHtml(challenge, dialogId, texts, lang);
        const dialogDiv = document.createElement('div')
        dialogDiv.innerHTML = dialog
        document.body.appendChild(dialogDiv)
    }

    /**
     * 1. show captcha dialog that prompts user to answer a question
     * 2. validate answer via server http API
     * 3. if ok, onValidationOk function is called
     * 3. if not ok, onValidationFailed function is called
     */
    function submitAfterChallengingUser(form, dialogId) {
        const dialog = document.getElementById(dialogId)
        dialog.showModal()
        dialog.addEventListener('close', () => {
            if (dialog.returnValue !== 'confirmed') {
                return
            }
            const answer = document.getElementById(`simple-captcha-dialog-response`).value
            form.querySelector('[name="simple-captcha-response"]').value = answer

            fetch('captcha.api.php',
                {headers: {'accept': 'application/json'}, method: 'POST', body: new FormData(form)}
            ).then(resp => {
                if (resp.ok) {
                    return resp.json().then(json => {
                        const {result, lang} = json
                        if (result === 'ok') {
                            console.log('simple-captcha: user response is correct')
                        } else {
                            onValidationFailed(result, lang)
                        }
                        return {result, lang}
                    })
                } else {
                    console.log('simple-captcha: failed to submit user response to server', resp)
                    throw resp
                }
            }).then(({result, lang}) => {
                if (result === 'ok') {
                    onValidationOk(form, lang)
                }
            })
        })
    }

    function getTokenWithChallenge() {
        return fetch('captcha.api.php', {headers: {'accept': 'application/json'}}
        ).then(resp => {
            if (resp.ok) {
                return resp.json().then(json => {
                    const {token, challenge, lang} = json
                    return {token, challenge, lang}
                })
            } else {
                console.log('simple-captcha: failed to receive token and challenge from server', resp)
                throw resp
            }
        })
    }

    renderCaptchaServerResult(texts);

    /**
     * Currently all captchized forms have the same token.
     * I.e. cracking one token or solving the single related challenge, allows submitting all forms on the current page.
     *
     * 1. Loads captcha challenge from server
     * 2. Intercepts any captchizable form submit and shows and validates a captcha.
     * 3. The default onValidationOk function then submits the form as normal.
     *    The default onValidationFailed function stores the server side result message and reloads the page.
     */
    const tokenFields = document.querySelectorAll('[name="simple-captcha-token"]')
    if (tokenFields.length > 0) {
        getTokenWithChallenge().then(({token, challenge, lang}) => {
            if (document.getElementById(dialogId) == null) {
                createCaptchaConfirmDialog(challenge, lang, dialogId);
            }

            tokenFields.forEach(tokenEl => {
                tokenEl.value = token

                const form = tokenEl.closest('form')
                form.addEventListener('submit', (e) => {
                    // according to spec, .requestSubmit() should use form as submitter, but some browsers seem to use null
                    if (e.submitter != null && e.submitter !== form) {
                        submitAfterChallengingUser(form, dialogId);
                        e.stopPropagation()
                        e.preventDefault()
                        return false
                    }
                })
            })
        }).catch((e) => console.error(e))
    }
}
