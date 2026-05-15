(function () {
  const toggle = document.querySelector('.menu__button');
  const menu = document.querySelector('.nav__menu');
  const header = document.querySelector('header.nav');
  const links = menu ? Array.from(menu.querySelectorAll('a[href^="#"]')) : [];

  const closeMenu = () => {
    if (!toggle || !menu) return;
    document.body.classList.remove('menu-open');
    menu.classList.remove('is-open');
    toggle.classList.remove('is-active');
    toggle.setAttribute('aria-expanded', 'false');
  };

  const openMenu = () => {
    if (!toggle || !menu) return;
    document.body.classList.add('menu-open');
    menu.classList.add('is-open');
    toggle.classList.add('is-active');
    toggle.setAttribute('aria-expanded', 'true');
  };

  if (toggle && menu) {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      if (menu.classList.contains('is-open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });
  }

  const openModal = (id) => {
    const modal = document.getElementById('modal-' + id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  };

  const closeModal = (modal) => {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal.is-open')) {
      document.body.classList.remove('modal-open');
    }
  };

  document.querySelectorAll('[data-popup]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const id = el.getAttribute('data-popup');
      if (id) openModal(id);
    });
  });

  document.querySelectorAll('.modal [data-close]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const modal = el.closest('.modal');
      closeModal(modal);
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const open = document.querySelector('.modal.is-open');
      if (open) closeModal(open);
    }
  });

  document.querySelectorAll('a.js-download').forEach((link) => {
    link.addEventListener('click', async (e) => {
      e.preventDefault();
      const primary = link.getAttribute('href');
      const fallback = link.getAttribute('data-fallback');
      const filename = (link.getAttribute('data-file') || 'download');

      const triggerBlobDownload = (blob, suggested) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = suggested;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      };

      const fetchAsBlob = async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const ct = (r.headers.get('content-type') || '').toLowerCase();
        if (ct.startsWith('text/html')) throw new Error('html-response');
        return r.blob();
      };

      const ext = (fallback && fallback.split('.').pop()) || 'pdf';
      const suggested = filename + '.' + ext;

      try {
        const blob = await fetchAsBlob(primary);
        triggerBlobDownload(blob, suggested);
        return;
      } catch (_) { /* fall through */ }

      if (fallback) {
        try {
          const blob = await fetchAsBlob(fallback);
          triggerBlobDownload(blob, suggested);
          return;
        } catch (_) { /* fall through */ }

        const a = document.createElement('a');
        a.href = fallback;
        a.download = suggested;
        document.body.appendChild(a);
        a.click();
        a.remove();
      }
    });
  });

  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    const status = document.getElementById('contact-status');
    const submitBtn = contactForm.querySelector('.cf__submit');
    const fileInput = contactForm.querySelector('input[type="file"]');
    const fileLabel = contactForm.querySelector('.cf__file');
    const fileName = document.getElementById('signed-documents-name');
    const fileList = document.getElementById('signed-documents-list');

    const setStatus = (msg, type) => {
      if (!status) return;
      status.textContent = msg || '';
      status.classList.remove('is-success', 'is-error');
      if (type) status.classList.add('is-' + type);
    };

    const formatBytes = (bytes) => {
      if (!bytes && bytes !== 0) return '';
      const units = ['B', 'KB', 'MB', 'GB'];
      let i = 0;
      let v = bytes;
      while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
      return (i === 0 ? v : v.toFixed(1)) + ' ' + units[i];
    };

    const renderFileList = () => {
      if (!fileLabel || !fileName || !fileList) return;
      const files = Array.from(fileInput.files || []);
      if (files.length === 0) {
        fileName.textContent = 'No files selected';
        fileLabel.classList.remove('has-file');
        fileList.hidden = true;
        fileList.innerHTML = '';
        return;
      }
      fileLabel.classList.add('has-file');
      fileName.textContent = files.length === 1
        ? files[0].name
        : files.length + ' files selected';
      fileList.hidden = false;
      fileList.innerHTML = files.map((f) => {
        const safe = f.name.replace(/[<>"']/g, '');
        return '<li><span class="cf__file-item-name">' + safe + '</span>' +
               '<span class="cf__file-item-size">' + formatBytes(f.size) + '</span></li>';
      }).join('');
    };

    if (fileInput) {
      fileInput.addEventListener('change', renderFileList);
    }

    // When user arrives from the Documents page submit-flow, highlight the
    // file upload area and pre-select a sensible product interest so the
    // form clearly invites them to attach their signed documents.
    const params = new URLSearchParams(window.location.search);
    if (params.get('submit') === 'documents' || window.location.hash === '#submit-documents') {
      const upload = contactForm.querySelector('.cf__upload');
      if (upload) {
        upload.classList.add('cf__upload--highlight');
        setTimeout(() => {
          upload.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
      }
      const productSelect = contactForm.elements['product_interest'];
      if (productSelect && !productSelect.value) {
        productSelect.value = 'Other / General Inquiry';
      }
      const messageField = contactForm.elements['message'];
      if (messageField && !messageField.value) {
        messageField.value = 'Document Submission – please find my signed documents attached for your review.';
      }
      setStatus('Please attach your signed documents using the file upload below before sending.', null);
    }

    const requiredFields = ['company', 'contact_name', 'email', 'product_interest', 'message'];

    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = new FormData(contactForm);
      let firstInvalid = null;

      requiredFields.forEach((name) => {
        const field = contactForm.elements[name];
        if (!field) return;
        const value = (data.get(name) || '').toString().trim();
        if (!value) {
          field.classList.add('is-invalid');
          if (!firstInvalid) firstInvalid = field;
        } else {
          field.classList.remove('is-invalid');
        }
      });

      const emailField = contactForm.elements['email'];
      const email = (data.get('email') || '').toString().trim();
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailField.classList.add('is-invalid');
        if (!firstInvalid) firstInvalid = emailField;
      }

      if (firstInvalid) {
        setStatus('Please fill out the required fields correctly.', 'error');
        firstInvalid.focus();
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
      }
      setStatus('Sending your message...', null);

      try {
        const response = await fetch(contactForm.action || 'send.php', {
          method: 'POST',
          body: data,
          headers: { 'Accept': 'application/json' },
        });

        let payload = null;
        try { payload = await response.json(); } catch (_) {}

        if (response.ok && payload && payload.ok) {
          setStatus(payload.message || 'Message sent. Our team will get back to you shortly.', 'success');
          contactForm.reset();
          renderFileList();
        } else {
          const errMsg = (payload && payload.error)
            || 'We could not send your message. Please try again or email hc@hcleerimportex.com.';
          setStatus(errMsg, 'error');
          if (payload && Array.isArray(payload.missing)) {
            payload.missing.forEach((name) => {
              const field = contactForm.elements[name];
              if (field) field.classList.add('is-invalid');
            });
          }
        }
      } catch (err) {
        setStatus('Network error. Please check your connection and try again.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-loading');
        }
      }
    });

    contactForm.addEventListener('input', (e) => {
      if (e.target && e.target.classList) e.target.classList.remove('is-invalid');
    });
  }

})();
