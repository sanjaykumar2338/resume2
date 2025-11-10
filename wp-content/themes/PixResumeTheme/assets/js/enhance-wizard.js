(function () {
  const config = window.resumeEnhance || {};
  const exportEndpoint = config.exportEndpoint || '';
  const pricingUrl = config.pricingUrl || '/pricing/';
  const STORAGE_KEY = config.storageKey || 'rai_wizard_v1';
  const rootState = loadState();
  const enhanceState = rootState.enhance || {
    step: 1,
    goals: [],
    fields: {
      role: '',
      industry: '',
      years: '',
    },
    fileMeta: null,
    analysis: null,
  };
  rootState.enhance = enhanceState;
  window.resumeState = rootState;

  const stepSections = document.querySelectorAll('[data-step]');
  const stepPills = document.querySelectorAll('[data-step-pill]');
  const fileInput = document.getElementById('enhance-file');
  const fileMetaEl = document.getElementById('enhance-file-meta');
  const analyzeBtn = document.getElementById('enhance-analyze');
  const goalInputs = document.querySelectorAll('.goal-pill input');
  const roleInput = document.getElementById('enhance-role');
  const industryInput = document.getElementById('enhance-industry');
  const yearsInput = document.getElementById('enhance-years');
  const cardsContainer = document.getElementById('enhance-cards');
  const previewPane = document.getElementById('enhance-preview');
  const scoreBadge = document.getElementById('enhance-score');
  const copyBtn = document.getElementById('enhance-copy');
  const pdfBtn = document.getElementById('enhance-download-pdf');
  const docxBtn = document.getElementById('enhance-download-docx');
  const unlockModal = document.getElementById('unlock-modal');

  let uploadFile = null;

  init();

  function init() {
    hydrateFromState();
    bindNavigation();
    bindInputs();
    bindGoals();
    bindActions();
    showStep(enhanceState.step || 1, true);
  }

  function loadState() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      return stored ? JSON.parse(stored) : {};
    } catch (error) {
      console.warn('Unable to parse wizard state', error);
      return {};
    }
  }

  function persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(rootState));
    } catch (error) {
      console.warn('Unable to persist wizard state', error);
    }
  }

  function hydrateFromState() {
    if (enhanceState.fileMeta) {
      fileMetaEl.textContent = enhanceState.fileMeta;
    }

    if (enhanceState.goals && enhanceState.goals.length) {
      goalInputs.forEach((input) => {
        input.checked = enhanceState.goals.includes(input.value);
      });
    }

    if (enhanceState.fields) {
      roleInput.value = enhanceState.fields.role || '';
      industryInput.value = enhanceState.fields.industry || '';
      yearsInput.value = enhanceState.fields.years || '';
    }

    if (enhanceState.analysis) {
      renderResults(enhanceState.analysis);
      copyBtn.disabled = false;
    }
  }

  function bindNavigation() {
    document.querySelectorAll('[data-next-step]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = parseInt(btn.dataset.nextStep, 10);
        if (target === 2 && !uploadFile && !enhanceState.fileMeta) {
          notify('warning', config.messages?.missingFile || 'Please upload a resume first.');
          return;
        }
        showStep(target);
      });
    });

    document.querySelectorAll('[data-prev-step]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = parseInt(btn.dataset.prevStep, 10);
        showStep(target);
      });
    });
  }

  function bindInputs() {
    if (fileInput) {
      fileInput.addEventListener('change', () => {
        if (!fileInput.files || !fileInput.files.length) {
          return;
        }
        uploadFile = fileInput.files[0];
        const meta = `${uploadFile.name} • ${(uploadFile.size / 1024).toFixed(1)} KB`;
        enhanceState.fileMeta = meta;
        fileMetaEl.textContent = meta;
        persist();
      });
    }

    roleInput.addEventListener('input', debounce(() => {
      enhanceState.fields.role = roleInput.value.trim();
      persist();
    }, 300));
    industryInput.addEventListener('input', debounce(() => {
      enhanceState.fields.industry = industryInput.value.trim();
      persist();
    }, 300));
    yearsInput.addEventListener('input', debounce(() => {
      enhanceState.fields.years = yearsInput.value.trim();
      persist();
    }, 300));
  }

  function bindGoals() {
    goalInputs.forEach((input) => {
      input.addEventListener('change', () => {
        const selected = Array.from(goalInputs)
          .filter((el) => el.checked)
          .map((el) => el.value);
        enhanceState.goals = selected;
        persist();
      });
    });
  }

  function bindActions() {
    if (analyzeBtn) {
      analyzeBtn.addEventListener('click', handleAnalyze);
    }

    copyBtn?.addEventListener('click', handleCopy);

    [pdfBtn, docxBtn].forEach((btn) => {
      if (!btn) {
        return;
      }
      btn.addEventListener('click', () => handleDownload(btn.dataset.format || 'pdf'));
    });

    document.querySelectorAll('[data-unlock-close]').forEach((btn) => {
      btn.addEventListener('click', closeUnlockModal);
    });

    document.querySelectorAll('[data-unlock-start]').forEach((btn) => {
      btn.addEventListener('click', () => {
        try {
          sessionStorage.setItem('rai_return', window.location.href);
        } catch (error) {
          console.warn(error);
        }
        closeUnlockModal();
        if (window.fixResumePaywall && typeof window.fixResumePaywall.startCheckout === 'function') {
          window.fixResumePaywall.startCheckout('primary');
        }
      });
    });
  }

  function showStep(step, skipPersist) {
    const clamped = Math.min(Math.max(step, 1), 3);
    stepSections.forEach((section) => {
      const match = parseInt(section.dataset.step, 10) === clamped;
      section.hidden = !match;
    });
    stepPills.forEach((pill) => {
      const match = parseInt(pill.dataset.stepPill, 10) === clamped;
      pill.classList.toggle('active', match);
    });
    enhanceState.step = clamped;
    if (!skipPersist) {
      persist();
    }
  }

  async function handleAnalyze() {
    if (!uploadFile) {
      notify('warning', config.messages?.missingFile || 'Please upload a resume.');
      return;
    }

    const goals = enhanceState.goals.length ? enhanceState.goals : ['grammar', 'keywords', 'formatting'];

    const formData = new FormData();
    formData.append('resume_file', uploadFile);
    formData.append('mode', 'enhance');
    formData.append('goals', goals.join(','));
    if (roleInput.value) {
      formData.append('target_role', roleInput.value);
    }
    if (industryInput.value) {
      formData.append('industry', industryInput.value);
    }
    if (yearsInput.value) {
      formData.append('experience', yearsInput.value);
    }

    toggleLoading(true);
    try {
      const response = await fetch(config.optimizeEndpoint, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const body = await response.json();
      if (!response.ok || !body?.success) {
        throw new Error(body?.error || config.messages?.error || 'Unable to analyze the resume.');
      }
      enhanceState.analysis = body.data;
      persist();
      renderResults(body.data);
      copyBtn.disabled = false;
      showStep(3);
      notify('success', config.messages?.success || 'Suggestions ready!');
    } catch (error) {
      console.error(error);
      notify('error', error.message || config.messages?.error || 'Analysis failed.');
    } finally {
      toggleLoading(false);
    }
  }

  function renderResults(data = {}) {
    const cards = [
      { key: 'grammar', label: 'Grammar & Clarity' },
      { key: 'keywords', label: 'Keywords (ATS)' },
      { key: 'formatting', label: 'Formatting & Structure' },
      { key: 'summary', label: 'Executive Summary' },
    ];

    const list = cards
      .map((card) => {
        if (!data[card.key]) {
          return '';
        }
        return `<article class="review-card"><h4>${card.label}</h4><p>${formatAsHtml(data[card.key])}</p></article>`;
      })
      .filter(Boolean)
      .join('');

    cardsContainer.innerHTML = list || `<p class="wizard-placeholder">${config.messages?.noResults || 'No suggestions returned yet.'}</p>`;
    scoreBadge.textContent = typeof data.score === 'number' ? `${data.score}` : '--';
    previewPane.innerHTML = data.resume_document
      ? data.resume_document
      : `<p class="wizard-placeholder">${config.messages?.noPreview || 'Preview will appear after the analysis.'}</p>`;
  }

  async function handleCopy() {
    if (!enhanceState.analysis) {
      return;
    }
    const text = convertToPlainText(enhanceState.analysis.resume_document || '');
    try {
      await navigator.clipboard.writeText(text);
      notify('success', config.messages?.copied || 'Suggestions copied to clipboard.');
    } catch (error) {
      notify('error', config.messages?.copyError || 'Unable to copy suggestions.');
    }
  }

  function base64ToBlob(encoded, mimeType = 'application/octet-stream') {
    const binary = atob(encoded);
    const length = binary.length;
    const buffer = new Uint8Array(length);
    for (let i = 0; i < length; i += 1) {
      buffer[i] = binary.charCodeAt(i);
    }
    return new Blob([buffer], { type: mimeType });
  }

  function downloadBinaryFile(fileData = {}) {
    if (!fileData.file) {
      return;
    }
    const blob = base64ToBlob(fileData.file, fileData.mime_type || 'application/octet-stream');
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileData.filename || 'enhanced-resume';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
  }

  async function handleDownload(format) {
    if (!enhanceState.analysis) {
      notify('warning', config.messages?.missingAnalysis || 'Run an analysis first.');
      return;
    }
    if (!config.canDownload) {
      openUnlockModal();
      return;
    }

    if (!exportEndpoint) {
      notify('error', config.messages?.exportError || 'Unable to export your resume right now.');
      return;
    }

    const normalizedFormat = format === 'docx' ? 'docx' : 'pdf';
    const analysis = enhanceState.analysis || {};
    const payload = {
      resume_document: analysis.resume_document || '',
      score: analysis.score,
      grammar: analysis.grammar || '',
      keywords: analysis.keywords || '',
      formatting: analysis.formatting || '',
      summary: analysis.summary || '',
    };

    if (!payload.resume_document) {
      notify('error', config.messages?.exportError || 'Unable to export your resume.');
      return;
    }

    toggleLoading(true, config.messages?.exporting || 'Preparing your download…');

    try {
      const response = await fetch(exportEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type: 'enhance',
          format: normalizedFormat,
          filename: normalizedFormat === 'docx' ? 'enhanced-resume.docx' : 'enhanced-resume.pdf',
          data: payload,
        }),
      });

      if (response.status === 401 || response.status === 403) {
        openUnlockModal();
        return;
      }

      const body = await response.json();
      if (!response.ok || !body?.success || !body?.data?.file) {
        throw new Error(body?.error || config.messages?.exportError || 'Unable to export your resume.');
      }

      downloadBinaryFile(body.data);
      notify('success', config.messages?.download || 'Your optimized resume is downloading.');
    } catch (error) {
      console.error(error);
      notify('error', error.message || config.messages?.exportError || 'Unable to export your resume.');
    } finally {
      toggleLoading(false);
    }
  }

  function openUnlockModal() {
    if (unlockModal) {
      unlockModal.classList.add('is-open');
    } else if (window.fixResumePaywall?.redirectToPricing) {
      window.fixResumePaywall.redirectToPricing();
    } else if (pricingUrl) {
      window.location.href = pricingUrl;
    }
  }

  function closeUnlockModal() {
    if (unlockModal) {
      unlockModal.classList.remove('is-open');
    }
  }

  function toggleLoading(isLoading, message) {
    if (!window.Swal) {
      return;
    }
    if (isLoading) {
      Swal.fire({
        title: message || config.messages?.loading || 'Analyzing your resume…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });
    } else if (Swal.isVisible()) {
      Swal.close();
    }
  }

  function notify(type, message) {
    if (window.Swal) {
      Swal.fire({
        icon: type,
        title: message,
        timer: type === 'success' ? 1700 : undefined,
        showConfirmButton: type !== 'success',
      });
      return;
    }
    alert(message);
  }

  function convertToPlainText(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
  }

  function formatAsHtml(content) {
    if (!content) {
      return '';
    }
    const temp = document.createElement('div');
    temp.textContent = content;
    return temp.innerHTML.replace(/\n/g, '<br>');
  }

  function debounce(fn, wait = 300) {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(null, args), wait);
    };
  }
})();
