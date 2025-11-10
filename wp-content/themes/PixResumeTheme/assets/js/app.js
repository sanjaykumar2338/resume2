(function () {
  const apiConfig = window.fixResumeApi || {};
  const endpoint = apiConfig.endpoint || '/wp-json/resume-ai/v1/optimize';
  const currentUserEmail = apiConfig.currentUserEmail || '';
  const canDownload = Boolean(apiConfig.canDownload);
  const pricingUrl = apiConfig.pricingUrl || '/pricing/';
  const messages = apiConfig.messages || {};

  const state = {
    lastUpload: { name: 'resume', extension: 'txt' },
    lastData: null,
  };

  const t = (key, fallback) => messages[key] || fallback;

  const showModal = (options) => {
    if (window.Swal) {
      Swal.fire(options);
      return true;
    }
    return false;
  };

  const showLoading = () => {
    if (!showModal({
      title: t('loading', 'Analyzing your resume…'),
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    })) {
      console.log(t('loading', 'Analyzing your resume…'));
    }
  };

  const hideLoading = () => {
    if (window.Swal && Swal.isLoading()) {
      Swal.close();
    }
  };

  const showSuccess = () => {
    if (!showModal({
      icon: 'success',
      title: t('success', 'Suggestions ready!'),
      timer: 1900,
      showConfirmButton: false,
    })) {
      alert(t('success', 'Suggestions ready!'));
    }
  };

  const showWarning = (message) => {
    if (!showModal({ icon: 'warning', title: t('missing', message), text: message })) {
      alert(message);
    }
  };

  const showError = (message) => {
    if (!showModal({ icon: 'error', title: t('error', 'We couldn’t analyze your resume. Please try again.'), text: message })) {
      alert(message);
    }
  };

  const downloadBlob = (blob, filename, successMessage) => {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 5000);

    if (!showModal({ icon: 'success', title: successMessage || t('download', 'Your optimized resume is downloading.'), timer: 1500, showConfirmButton: false })) {
      alert(successMessage || t('download', 'Your optimized resume is downloading.'));
    }
  };

  const normalizeExtension = (ext) => {
    const lower = (ext || '').toLowerCase();
    if (['pdf', 'doc', 'docx', 'txt', 'rtf'].includes(lower)) {
      return lower;
    }
    return 'txt';
  };

  const buildTextDocument = (data) => {
    const lines = [];
    const score = typeof data.score === 'number' ? `${data.score}/100` : '--';
    lines.push('Optimized Resume\n');
    lines.push(`AI Score: ${score}`);
    lines.push('');
    lines.push('Professional Summary');
    lines.push(data.summary || '');
    lines.push('');
    lines.push('Grammar & Clarity');
    lines.push(data.grammar || '');
    lines.push('');
    lines.push('Keywords (ATS)');
    lines.push(data.keywords || '');
    lines.push('');
    lines.push('Formatting & Structure');
    lines.push(data.formatting || '');
    lines.push('');
    return lines.join('\n');
  };

  const buildHtmlDocument = (data) => {
    const score = typeof data.score === 'number' ? `${data.score}/100` : '--';
    return `<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Optimized Resume</title><style>body{font-family:Arial,Helvetica,sans-serif;line-height:1.6;padding:32px;color:#1f2933;}h1{font-size:28px;margin-bottom:16px;}h2{font-size:20px;margin:24px 0 8px;}p{margin:0 0 12px;}section{margin-bottom:18px;border-bottom:1px solid #e5e7eb;padding-bottom:16px;}section:last-child{border-bottom:none;padding-bottom:0;}</style></head><body><h1>Optimized Resume</h1><p><strong>AI Score:</strong> ${score}</p><section><h2>Professional Summary</h2><p>${(data.summary || '').replace(/\n/g, '<br>')}</p></section><section><h2>Grammar & Clarity</h2><p>${(data.grammar || '').replace(/\n/g, '<br>')}</p></section><section><h2>Keywords (ATS)</h2><p>${(data.keywords || '').replace(/\n/g, '<br>')}</p></section><section><h2>Formatting & Structure</h2><p>${(data.formatting || '').replace(/\n/g, '<br>')}</p></section></body></html>`;
  };

  const renderResults = (container, data) => {
    if (!container) {
      return;
    }

    state.lastData = data;

    const ext = normalizeExtension(state.lastUpload.extension);
    const displayExt = ext === 'doc' ? 'doc' : ext === 'docx' ? 'docx' : ext === 'pdf' ? 'pdf' : 'txt';
    const downloadLabel = `${t('downloadLabel', 'Download optimized resume')} (.${displayExt})`;
    const score = typeof data.score === 'number' ? `${data.score}/100` : '--';

    const cards = [
      { title: 'Grammar & Clarity', key: 'grammar' },
      { title: 'Keywords (ATS)', key: 'keywords' },
      { title: 'Formatting & Structure', key: 'formatting' },
    ];

    const summaryCard = data.summary
      ? `<article class="upload-results__card"><h3>Executive Summary</h3><p>${(data.summary || '').replace(/\n/g, '<br>')}</p></article>`
      : '';

    const cardsMarkup = cards
      .map(({ title, key }) => {
        const value = data[key];
        if (!value) {
          return '';
        }
        return `<article class="upload-results__card"><h3>${title}</h3><p>${value.replace(/\n/g, '<br>')}</p></article>`;
      })
      .join('');

    container.hidden = false;
    container.innerHTML = `
      <div class="upload-results__meta">
        <span class="upload-results__score">${score}</span>
        <div class="upload-results__actions">
          <button class="button ghost upload-results__copy" type="button">${t('copy', 'Copy suggestions')}</button>
          <button class="button primary upload-results__download" type="button">${downloadLabel}</button>
        </div>
      </div>
      <div class="upload-results__grid">
        ${cardsMarkup}
        ${summaryCard}
      </div>
    `;

    const copyButton = container.querySelector('.upload-results__copy');
    if (copyButton) {
      copyButton.addEventListener('click', async () => {
        const textDoc = buildTextDocument(state.lastData || {});
        try {
          await navigator.clipboard.writeText(textDoc.trim());
          if (!showModal({ icon: 'success', title: t('copy', 'Suggestions copied to clipboard'), timer: 1400, showConfirmButton: false })) {
            alert(t('copy', 'Suggestions copied to clipboard'));
          }
        } catch (error) {
          console.error(error);
          if (!showModal({ icon: 'error', title: t('copyFail', 'Unable to copy suggestions. Please copy manually.') })) {
            alert(t('copyFail', 'Unable to copy suggestions. Please copy manually.'));
          }
        }
      });
    }

    const downloadButton = container.querySelector('.upload-results__download');
    if (downloadButton) {
      downloadButton.addEventListener('click', () => {
        downloadOptimizedResume();
      });
    }
  };

  const downloadOptimizedResume = () => {
    if (!canDownload) {
      if (window.fixResumePaywall && typeof window.fixResumePaywall.redirectToPricing === 'function') {
        window.fixResumePaywall.redirectToPricing();
      } else {
        window.location.href = pricingUrl;
      }
      return;
    }

    const data = state.lastData;
    if (!data) {
      showWarning(t('downloadFail', 'No optimized resume available yet. Please rerun the analysis.'));
      return;
    }

    const ext = normalizeExtension(state.lastUpload.extension);
    const baseName = (state.lastUpload.name || 'optimized-resume').replace(/\.[^.]+$/, '');
    const textDoc = data.resume_document || buildTextDocument(data);
    const htmlDoc = buildHtmlDocument(data);

    if (ext === 'pdf' && window.jspdf && window.jspdf.jsPDF) {
      const doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'a4' });
      const margin = 54;
      const maxWidth = doc.internal.pageSize.getWidth() - margin * 2;
      const pageHeight = doc.internal.pageSize.getHeight();
      let cursorY = margin;

      const ensureSpace = (height = 0) => {
        if (cursorY + height > pageHeight - margin) {
          doc.addPage();
          cursorY = margin;
        }
      };

      const addSection = (title, content) => {
        ensureSpace(36);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.text(title, margin, cursorY);
        cursorY += 20;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(12);
        const lines = doc.splitTextToSize((content || '-').replace(/\r/g, ''), maxWidth);
        lines.forEach((line) => {
          ensureSpace(18);
          doc.text(line, margin, cursorY);
          cursorY += 16;
        });
        cursorY += 10;
      };

      addSection('AI Score', `Score: ${typeof data.score === 'number' ? data.score : '--'}/100`);
      addSection('Professional Summary', data.summary || '-');
      addSection('Grammar & Clarity', data.grammar || '-');
      addSection('Keywords (ATS)', data.keywords || '-');
      addSection('Formatting & Structure', data.formatting || '-');

      doc.save(`${baseName}-optimized.pdf`);
      if (!showModal({ icon: 'success', title: t('download', 'Your optimized resume is downloading.'), timer: 1500, showConfirmButton: false })) {
        alert(t('download', 'Your optimized resume is downloading.'));
      }
      return;
    }

    if (ext === 'docx' && window.htmlDocx) {
      const blob = window.htmlDocx.asBlob(htmlDoc);
      downloadBlob(blob, `${baseName}-optimized.docx`);
      return;
    }

    if (ext === 'doc') {
      const blob = new Blob([htmlDoc], { type: 'application/msword' });
      downloadBlob(blob, `${baseName}-optimized.doc`);
      return;
    }

    const blob = new Blob([textDoc], { type: 'text/plain;charset=utf-8' });
    downloadBlob(blob, `${baseName}-optimized.txt`);
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.upload-form');
    if (!form) {
      return;
    }

    let resultsContainer = document.querySelector('.upload-results');
    if (!resultsContainer) {
      resultsContainer = document.createElement('div');
      resultsContainer.className = 'upload-results';
      resultsContainer.hidden = true;
      form.insertAdjacentElement('afterend', resultsContainer);
    }

    const button = form.querySelector('button');
    if (button) {
      button.type = 'submit';
    }

    const fileInput = form.querySelector('input[name="resume"]');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (!fileInput || !fileInput.files || !fileInput.files.length) {
        showWarning(t('missing', 'Please select a resume file before requesting suggestions.'));
        return;
      }

      const file = fileInput.files[0];
      const match = (file.name || '').toLowerCase().match(/\.([a-z0-9]+)$/);
      const extension = normalizeExtension(match ? match[1] : '');
      state.lastUpload = {
        name: file.name || 'resume',
        extension,
      };

      const formData = new FormData();
      formData.append('resume_file', file);

      const roleField = form.querySelector('input[name="role"]');
      if (roleField && roleField.value) {
        formData.append('target_role', roleField.value);
      }

      const priorityField = form.querySelector('select[name="priority"]');
      if (priorityField && priorityField.value) {
        formData.append('priority', priorityField.value);
      }

      const emailField = form.querySelector('input[name="email"], input[name="user_email"]');
      if (currentUserEmail) {
        formData.append('user_email', currentUserEmail);
      } else if (emailField && emailField.value) {
        formData.append('user_email', emailField.value);
      }

      showLoading();

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });

        const payload = await response.json();

        if (!response.ok || !payload?.success) {
          throw new Error(payload?.error || t('error', 'We couldn’t analyze your resume. Please try again.'));
        }

        renderResults(resultsContainer, payload.data || {});
        showSuccess();
      } catch (error) {
        console.error(error);
        showError(error.message || t('error', 'We couldn’t analyze your resume. Please try again.'));
      } finally {
        hideLoading();
      }
    });
  });
})();
