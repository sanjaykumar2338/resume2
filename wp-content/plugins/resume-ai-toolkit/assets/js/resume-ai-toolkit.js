(function () {
  const escapeHtml = (str = '') => String(str).replace(/[&<>\"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const nl2br = (str = '') => escapeHtml(str).replace(/\n/g, '<br>');

  const renderError = (container, message) => {
    console.error('Resume AI Toolkit error:', message);
    if (!container) {
      return;
    }
    container.innerHTML = `<div class="ai-card error">${escapeHtml(message || 'Something went wrong. Please try again.')}</div>`;
  };

  const renderData = (container, data) => {
    if (!container) {
      return;
    }

    const cards = [
      { title: 'Grammar & Clarity', text: data?.grammar },
      { title: 'Keywords (ATS)', text: data?.keywords },
      { title: 'Formatting & Structure', text: data?.formatting },
    ];

    if (data?.summary) {
      cards.push({ title: 'Executive Summary', text: data.summary });
    }

    const score = typeof data?.score === 'number' ? data.score : '--';

    const cardsMarkup = cards
      .map((card) => `
        <div class="ai-card">
          <h3>${escapeHtml(card.title)}</h3>
          <p>${nl2br(card.text || '')}</p>
        </div>
      `)
      .join('');

    container.innerHTML = `
      <div class="ai-results__inner">
        <div class="ai-score">AI Score: ${escapeHtml(String(score))}/100</div>
        ${cardsMarkup}
        <button type="button" id="copyAll" class="ai-copy">Copy All</button>
      </div>
    `;

    const copyBtn = container.querySelector('#copyAll');
    if (copyBtn && navigator.clipboard) {
      copyBtn.addEventListener('click', async () => {
        const plain = [
          `Score: ${score}/100`,
          '',
          `GRAMMAR & CLARITY\n${data?.grammar || ''}`,
          '',
          `KEYWORDS (ATS)\n${data?.keywords || ''}`,
          '',
          `FORMATTING & STRUCTURE\n${data?.formatting || ''}`,
          data?.summary ? `\nEXECUTIVE SUMMARY\n${data.summary}` : '',
        ].join('\n');

        try {
          await navigator.clipboard.writeText(plain.trim());
          const originalLabel = copyBtn.textContent;
          copyBtn.textContent = 'Copied';
          copyBtn.disabled = true;
          setTimeout(() => {
            copyBtn.textContent = originalLabel;
            copyBtn.disabled = false;
          }, 1500);
        } catch (error) {
          console.error('Copy failed', error);
        }
      });
    }
  };

  const sendRequest = async (form, url) => {
    const response = await fetch(url, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
    });

    if (!response.ok) {
      let message = `Request failed (${response.status})`;
      try {
        const body = await response.json();
        if (body?.error) {
          message = body.error;
        }
      } catch (error) {
        // Ignore JSON parse errors for non-JSON responses.
      }

      throw new Error(message);
    }

    return response.json();
  };

  const attachHandler = () => {
    const form = document.getElementById('resume-optimizer-form');
    if (!form) {
      console.error('Resume AI Toolkit: form element not found.');
      return;
    }

    const config = window.resumeAiToolkit || {};
    const loadingText = config.loadingText || 'Analyzing…';
    const successText = config.successText || 'Resume optimized!';
    const errorTitle = config.errorTitle || 'Something went wrong';
    const warningTitle = config.warningTitle || 'Heads up';
    const fileMissingMsg = config.fileMissing || 'Please attach your resume before submitting.';

    const wrapper = form.closest('.resume-optimizer-wrapper');
    if (wrapper) {
      const wrapperParent = wrapper.parentElement;
      if (wrapperParent && wrapperParent.tagName === 'P') {
        wrapperParent.replaceWith(wrapper);
      }

      Array.from(wrapper.childNodes).forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          const cleaned = node.textContent.trim().replace(/['"]/g, '');
          if (cleaned === 'Analyzing…' || cleaned === 'Analyzing...') {
            node.textContent = '';
          }
        }
      });
      Array.from(wrapper.children).forEach((child) => {
        if (child.tagName === 'P') {
          const cleaned = child.textContent.trim().replace(/['"]/g, '');
          if (cleaned === 'Analyzing…' || cleaned === 'Analyzing...') {
            child.remove();
          }
        }
      });

      let sibling = wrapper.nextSibling;
      while (sibling) {
        const next = sibling.nextSibling;
        if (sibling.nodeType === Node.TEXT_NODE) {
          const cleaned = sibling.textContent.trim().replace(/['"]/g, '');
          if (cleaned === 'Analyzing…' || cleaned === 'Analyzing...') {
            sibling.parentNode.removeChild(sibling);
          }
        }
        sibling = next;
      }
    }

    let results = document.getElementById('ai-results');
    if (!results) {
      results = document.createElement('div');
      results.id = 'ai-results';
      results.className = 'ai-results';
      form.insertAdjacentElement('afterend', results);
    }
    results.setAttribute('aria-live', 'polite');
    results.setAttribute('aria-atomic', 'true');

    const showLoadingModal = () => {
      if (window.Swal) {
        Swal.fire({
          title: loadingText,
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          },
        });
      }
    };

    const closeLoadingModal = () => {
      if (window.Swal && Swal.isLoading()) {
        Swal.close();
      }
    };

    const showWarningModal = (message) => {
      if (window.Swal) {
        Swal.fire({
          icon: 'warning',
          title: warningTitle,
          text: message,
        });
      }
    };

    const showErrorModal = (message) => {
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: errorTitle,
          text: message,
        });
      }
    };

    const showSuccessModal = () => {
      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: successText,
          timer: 1600,
          showConfirmButton: false,
        });
      }
    };

    const submitBtn = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      console.debug('Resume AI Toolkit: submit intercepted');

      if (results) {
        results.innerHTML = '';
      }

      const fileInput = form.querySelector('#resume_file');
      if (!fileInput || !fileInput.files || !fileInput.files.length) {
        renderError(results, fileMissingMsg);
        closeLoadingModal();
        showWarningModal(fileMissingMsg);
        return;
      }

      showLoadingModal();

      let originalText = '';
      if (submitBtn) {
        originalText = submitBtn.textContent || '';
        submitBtn.disabled = true;
        submitBtn.textContent = loadingText;
      }

      const attemptQueue = [];
      if (config.endpoint) {
        attemptQueue.push(config.endpoint);
      }
      if (config.alt && config.alt !== config.endpoint) {
        attemptQueue.push(config.alt);
      }

      attemptQueue.push('/wp-json/resume-ai/v1/optimize');
      attemptQueue.push('/index.php?rest_route=/resume-ai/v1/optimize');

      let lastError = null;

      for (const url of attemptQueue) {
        if (!url) {
          continue;
        }

        try {
          console.debug('Resume AI Toolkit: trying endpoint', url);
          const payload = await sendRequest(form, url);
          console.debug('Resume AI Toolkit: response received', payload);
          if (!payload?.success) {
            throw new Error(payload?.error || 'No suggestions were returned.');
          }

          renderData(results, payload.data || {});
          lastError = null;
           closeLoadingModal();
           showSuccessModal();
          break;
        } catch (error) {
          lastError = error;
        }
      }

      if (lastError) {
        renderError(results, lastError.message);
        closeLoadingModal();
        showErrorModal(lastError.message);
      }

      closeLoadingModal();

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText || 'Optimize Now';
        console.debug('Resume AI Toolkit: button reset');
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachHandler);
  } else {
    attachHandler();
  }
})();
