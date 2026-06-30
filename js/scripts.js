/* =========================================================
   GLOBAL DOM READY WRAPPER
   All DOM-based scripts are placed inside this to prevent
   undefined element errors and avoid override conflicts.
========================================================= */
document.addEventListener("DOMContentLoaded", function () {

    /* =========================================================
       1. NAVBAR TOGGLER ICON SWITCH (bars <-> times)
    ========================================================= */
    const toggleButton = document.getElementById("navToggle");

    if (toggleButton) {
        const icon = toggleButton.querySelector("i");

        toggleButton.addEventListener("click", function () {
            if (!icon) return;

            if (icon.classList.contains("fa-bars")) {
                icon.classList.remove("fa-bars");
                icon.classList.add("fa-times");
            } else {
                icon.classList.remove("fa-times");
                icon.classList.add("fa-bars");
            }
        });
    }


    /* =========================================================
       2. HERO TYPING TEXT ANIMATION
    ========================================================= */
    const words = ["Design", "Ideas", "Identity", "Clarity", "Creativity"];
    const span = document.getElementById("changing-word");

    if (span) {
        let wordIndex = 0;
        let charIndex = 0;
        let typing = true;

        function typeLoop() {
            const currentWord = words[wordIndex];

            if (typing) {
                span.textContent = currentWord.slice(0, charIndex + 1);
                charIndex++;

                if (charIndex === currentWord.length) {
                    typing = false;
                    setTimeout(typeLoop, 1500);
                } else {
                    setTimeout(typeLoop, 100);
                }
            } else {
                span.textContent = currentWord.slice(0, charIndex - 1);
                charIndex--;

                if (charIndex === 0) {
                    typing = true;
                    wordIndex = (wordIndex + 1) % words.length;
                    setTimeout(typeLoop, 300);
                } else {
                    setTimeout(typeLoop, 50);
                }
            }
        }

        typeLoop();
    }


    /* =========================================================
       3. ENHANCED FILE UPLOAD LABEL UPDATE
    ========================================================= */
    const attachmentInput = document.getElementById('attachment');

    if (attachmentInput) {
        attachmentInput.addEventListener('change', function (e) {
            const fileName = e.target.files[0]?.name;
            const fileText = this.nextElementSibling?.querySelector('.file-text');

            if (!fileText) return;

            if (fileName) {
                fileText.textContent = fileName;
                fileText.style.color = '#024442';
            } else {
                fileText.textContent = 'Choose file';
                fileText.style.color = '#64748b';
            }
        });
    }


    /* =========================================================
       4. QUILL EDITOR INITIALIZATION
    ========================================================= */
    const editorElement = document.getElementById('public-editor');
    const publicInput   = document.getElementById('public_description_input');

    if (editorElement && publicInput && typeof Quill !== "undefined") {

        const publicEditor = new Quill('#public-editor', {
            modules: {
                toolbar: [
                    [{ 'header': [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'blockquote'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            },
            placeholder: 'Write a compelling narrative for this project...',
            theme: 'snow'
        });

        publicInput.value = publicEditor.root.innerHTML;

        publicEditor.on('text-change', function () {
            publicInput.value = publicEditor.root.innerHTML;
        });
    }

});


/* =========================================================
   5. CAREER PAGE TABLE FILTER (GLOBAL)
   Kept outside DOMContentLoaded — called via onkeyup in HTML.
========================================================= */
function filterTable() {
    const inputField = document.getElementById("searchRole");
    const table      = document.getElementById("positionsTable");

    if (!inputField || !table) return;

    const input = inputField.value.toLowerCase();
    const trs   = table.getElementsByTagName("tr");

    for (let i = 1; i < trs.length; i++) {
        const tds = trs[i].getElementsByTagName("td");
        let show  = false;

        for (let j = 0; j < tds.length; j++) {
            if (tds[j].textContent.toLowerCase().includes(input)) {
                show = true;
                break;
            }
        }

        trs[i].style.display = show ? "" : "none";
    }
}