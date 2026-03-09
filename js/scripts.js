
 // navbar togler
 document.addEventListener("DOMContentLoaded", function () {
        const toggleButton = document.getElementById("navToggle");
        const icon = toggleButton.querySelector("i");

        toggleButton.addEventListener("click", function () {
            if (icon.classList.contains("fa-bars")) {
                icon.classList.remove("fa-bars");
                icon.classList.add("fa-times"); // Change to close icon
            } else {
                icon.classList.remove("fa-times");
                icon.classList.add("fa-bars"); // Change back to menu icon
            }
        });
    });

//active class 
document.addEventListener("DOMContentLoaded", function() {
    const currentPage = window.location.pathname.split("/").pop();  
    const links = document.querySelectorAll(".navbar-nav .nav-link");

    links.forEach(link => {
        const linkPage = link.getAttribute("href");

        if (linkPage === currentPage) {
            link.classList.add("active");
        }
    });
});

//typing text animation


  const words = ["Design", "Ideas", "Identity", "Clarity", "Creativity"];
  let wordIndex = 0;
  let charIndex = 0;
  const span = document.getElementById("changing-word");

  function typeWord() {
    const currentWord = words[wordIndex];
    if (charIndex < currentWord.length) {
      span.textContent += currentWord.charAt(charIndex);
      charIndex++;
      setTimeout(typeWord, 100);
    } else {
      setTimeout(eraseWord, 1500);
    }
  }

  function eraseWord() {
    if (charIndex > 0) {
      span.textContent = span.textContent.slice(0, -1);
      charIndex--;
      setTimeout(eraseWord, 50);
    } else {
      wordIndex = (wordIndex + 1) % words.length;
      setTimeout(typeWord, 300);
    }
  }

  document.addEventListener("DOMContentLoaded", typeWord);




window.addEventListener('DOMContentLoaded', event => {

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Uncomment Below to persist sidebar toggle between refreshes
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sb-sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

});


// career page table search script 
function filterTable() {
  const input = document.getElementById("searchRole").value.toLowerCase();
  const table = document.getElementById("positionsTable");
  const trs = table.getElementsByTagName("tr");

  for (let i = 1; i < trs.length; i++) {
    const tds = trs[i].getElementsByTagName("td");
    let show = false;
    for (let j = 0; j < tds.length; j++) {
      if (tds[j].textContent.toLowerCase().includes(input)) {
        show = true;
      }
    }
    trs[i].style.display = show ? "" : "none";
  }
}


document.addEventListener('DOMContentLoaded', () => {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');

    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');

      faqItems.forEach(otherItem => {
        if (otherItem !== item) {
          otherItem.classList.remove('active');
        }
      });

      item.classList.toggle('active', !isActive);
    });
  });
});



        ///////////







        // Enhanced File Upload
document.getElementById('attachment')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileText = this.nextElementSibling.querySelector('.file-text');
    
    if (fileName) {
        fileText.textContent = fileName;
        fileText.style.color = '#024442';
    } else {
        fileText.textContent = 'Choose file';
        fileText.style.color = '#64748b';
    }
});

// Form Submission
document.getElementById('projectRequestForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('.btn-primary');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
    
    // Simulate form submission (replace with actual AJAX call)
    setTimeout(() => {
        // Reset button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        // Show success message
        alert('Thank you! Your project request has been submitted successfully.');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('projectRequestModal'));
        modal.hide();
        
        // Reset form
        this.reset();
        document.querySelector('.file-text').textContent = 'Choose file';
    }, 2000);
});


// PROJECT DETAILS FIELD JS
 
    // 1. Initialize the Editor
    var publicEditor = new Quill('#public-editor', {
        modules: {
            toolbar: [
                [{ 'header': [2, 3, false] }],
                ['bold', 'italic', 'underline', 'blockquote'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'clean']
            ]
        },
        placeholder: 'Write a compelling narrative for this project...',
        theme: 'snow'
    });

    // 2. Sync with the Hidden Input
    var publicInput = document.getElementById('public_description_input');
    
    // Set initial value on load
    publicInput.value = publicEditor.root.innerHTML;

    // Update hidden input on every keystroke
    publicEditor.on('text-change', function() {
        publicInput.value = publicEditor.root.innerHTML;
    });
