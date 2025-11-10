(function () {
  'use strict';

  const builderForm = document.getElementById('resume-builder-form');
  if (!builderForm) {
    return;
  }

  const scoreEl = document.getElementById('builder-score');
  const previewEl = document.getElementById('builder-preview');
  const resetBtn = document.getElementById('builder-reset');
  const copyBtn = document.getElementById('builder-copy');
  const downloadButtons = document.querySelectorAll('[data-download-format]');
  const summaryAiBtn = document.getElementById('builder-summary-ai');
  const stepSections = builderForm.querySelectorAll('[data-step]');
  const stepPills = document.querySelectorAll('[data-step-pill]');
  const unlockModal = document.getElementById('unlock-modal');
  const STORAGE_KEY = 'rai_wizard_v1';
  const userPrefill = (window.fixResumeBuilder && window.fixResumeBuilder.prefill) || {};
  const canDownload = Boolean(window.fixResumeBuilder && window.fixResumeBuilder.canDownload);
  const pricingUrl = (window.fixResumeBuilder && window.fixResumeBuilder.pricingUrl) || '/pricing/';
  const rewriteLabel = (window.fixResumeBuilder && window.fixResumeBuilder.labels && window.fixResumeBuilder.labels.rewriteBullet)
    ? window.fixResumeBuilder.labels.rewriteBullet
    : 'Rewrite bullet';
  const rootStorage = loadWizardState();
  const builderStorage = rootStorage.builder || {};
  rootStorage.builder = builderStorage;

  try {
    if (!builderStorage.draft && window.localStorage) {
      const legacy = window.localStorage.getItem('fixresume_builder_draft_v1');
      if (legacy) {
        builderStorage.draft = JSON.parse(legacy);
        window.localStorage.removeItem('fixresume_builder_draft_v1');
      }
    }
  } catch (error) {
    console.warn('Legacy builder draft migration failed', error);
  }

  window.resumeState = rootStorage;

  const state = {
    lastResponse: builderStorage.lastResponse || null,
    isHydrating: false,
  };
  if (state.lastResponse) {
    updateScore(state.lastResponse.score);
  }

  const showStep = (step, skipSave = false) => {
    if (!stepSections.length) {
      return;
    }
    const target = Math.min(Math.max(step, 1), stepSections.length);
    stepSections.forEach((section) => {
      const match = parseInt(section.dataset.step, 10) === target;
      section.hidden = !match;
    });
    stepPills.forEach((pill) => {
      const match = parseInt(pill.dataset.stepPill, 10) === target;
      pill.classList.toggle('active', match);
    });
    builderStorage.step = target;
    if (!skipSave) {
      saveWizardState();
    }
  };

  const initialStep = builderStorage.step || 1;
  showStep(initialStep, true);
  if (!builderStorage.step) {
    builderStorage.step = initialStep;
    saveWizardState();
  }

  document.querySelectorAll('[data-next-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = parseInt(button.dataset.nextStep, 10);
      if (Number.isNaN(target)) {
        return;
      }
      showStep(target);
    });
  });

  document.querySelectorAll('[data-prev-step]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = parseInt(button.dataset.prevStep, 10);
      if (Number.isNaN(target)) {
        return;
      }
      showStep(target);
    });
  });

  const debounce = (fn, delay = 300) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(null, args), delay);
    };
  };

  function loadWizardState() {
    try {
      const stored = window.localStorage ? window.localStorage.getItem(STORAGE_KEY) : null;
      return stored ? JSON.parse(stored) : {};
    } catch (error) {
      console.warn('Unable to parse wizard state', error);
      return {};
    }
  }

  function saveWizardState() {
    try {
      if (window.localStorage) {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(rootStorage));
      }
    } catch (error) {
      console.warn('Unable to persist wizard state', error);
    }
  }

  const escapeHtml = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const formatMultiline = (value = '') => escapeHtml(value).replace(/\n/g, '<br>');

  const updateScore = (value) => {
    if (!scoreEl) {
      return;
    }
    scoreEl.textContent = typeof value === 'number' ? value : '--';
  };

  const showModal = (options) => {
    if (window.Swal) {
      Swal.fire(options);
      return true;
    }
    return false;
  };

  const showLoading = () => {
    if (!showModal({
      title: fixResumeBuilder.messages.loading,
      allowOutsideClick: false,
      didOpen: () => window.Swal && Swal.showLoading(),
    })) {
      console.log(fixResumeBuilder.messages.loading);
    }
  };

  const hideLoading = () => {
    if (window.Swal && Swal.isLoading()) {
      Swal.close();
    }
  };

  const showWarning = (message) => {
    if (!showModal({ icon: 'warning', title: fixResumeBuilder.messages.errorTitle, text: message })) {
      alert(message);
    }
  };

  const showError = (message) => {
    if (!showModal({ icon: 'error', title: fixResumeBuilder.messages.errorTitle, text: message })) {
      alert(message);
    }
  };

  const showSuccess = () => {
    if (!showModal({ icon: 'success', title: fixResumeBuilder.messages.success, timer: 1600, showConfirmButton: false })) {
      alert(fixResumeBuilder.messages.success);
    }
  };

  const repeaterConfig = {
    employment: {
      container: document.getElementById('employment-list'),
      template: () => `
        <div class="repeatable-card">
          <button type="button" class="repeatable-remove" aria-label="Remove employment">&times;</button>
          <label class="input-field">
            <span>Job title</span>
            <input name="employment[][title]" type="text" />
          </label>
          <label class="input-field">
            <span>Company</span>
            <input name="employment[][company]" type="text" />
          </label>
          <div class="builder-grid">
            <label class="input-field">
              <span>Start date</span>
              <input name="employment[][start]" type="text" placeholder="Jan 2020" />
            </label>
            <label class="input-field">
              <span>End date</span>
              <input name="employment[][end]" type="text" placeholder="Present" />
            </label>
          </div>
          <label class="input-field">
            <span>Key achievements</span>
            <textarea name="employment[][summary]" rows="3"></textarea>
          </label>
          <div class="builder-inline-actions">
            <button type="button" class="button soft builder-rewrite">${rewriteLabel}</button>
          </div>
        </div>
      `,
    },
    education: {
      container: document.getElementById('education-list'),
      template: () => `
        <div class="repeatable-card">
          <button type="button" class="repeatable-remove" aria-label="Remove education">&times;</button>
          <label class="input-field">
            <span>School</span>
            <input name="education[][school]" type="text" />
          </label>
          <label class="input-field">
            <span>Degree</span>
            <input name="education[][degree]" type="text" />
          </label>
          <div class="builder-grid">
            <label class="input-field">
              <span>Start year</span>
              <input name="education[][start]" type="text" />
            </label>
            <label class="input-field">
              <span>End year</span>
              <input name="education[][end]" type="text" />
            </label>
          </div>
        </div>
      `,
    },
  };

  const addRepeaterItem = (type, initialData = {}) => {
    const config = repeaterConfig[type];
    if (!config || !config.container) {
      return null;
    }
    const wrapper = document.createElement('div');
    wrapper.innerHTML = config.template().trim();
    const card = wrapper.firstElementChild;
    config.container.appendChild(card);

    if (type === 'employment') {
      const selectors = {
        title: 'input[name="employment[][title]"]',
        company: 'input[name="employment[][company]"]',
        start: 'input[name="employment[][start]"]',
        end: 'input[name="employment[][end]"]',
        summary: 'textarea[name="employment[][summary]"]',
      };
      Object.keys(selectors).forEach((key) => {
        const field = card.querySelector(selectors[key]);
        if (field && initialData[key]) {
          field.value = initialData[key];
        }
      });
    }

    if (type === 'education') {
      const selectors = {
        school: 'input[name="education[][school]"]',
        degree: 'input[name="education[][degree]"]',
        start: 'input[name="education[][start]"]',
        end: 'input[name="education[][end]"]',
      };
      Object.keys(selectors).forEach((key) => {
        const field = card.querySelector(selectors[key]);
        if (field && initialData[key]) {
          field.value = initialData[key];
        }
      });
    }

    return card;
  };

  document.querySelectorAll('[data-add]').forEach((button) => {
    button.addEventListener('click', () => {
      addRepeaterItem(button.dataset.add);
    });
  });

  const persistDraft = (data) => {
    if (state.isHydrating) {
      return;
    }
    builderStorage.draft = data;
    saveWizardState();
  };

  const removeDraft = () => {
    delete builderStorage.draft;
    saveWizardState();
  };

  const collectRepeatable = (type) => {
    const config = repeaterConfig[type];
    if (!config || !config.container) {
      return [];
    }
    const cards = Array.from(config.container.querySelectorAll('.repeatable-card'));
    return cards
      .map((card) => {
        if (type === 'employment') {
          const pick = (selector) => {
            const field = card.querySelector(selector);
            return field ? field.value.trim() : '';
          };
          return {
            title: pick('input[name="employment[][title]"]'),
            company: pick('input[name="employment[][company]"]'),
            start: pick('input[name="employment[][start]"]'),
            end: pick('input[name="employment[][end]"]'),
            summary: pick('textarea[name="employment[][summary]"]'),
          };
        }
        const pickEdu = (selector) => {
          const field = card.querySelector(selector);
          return field ? field.value.trim() : '';
        };
        return {
          school: pickEdu('input[name="education[][school]"]'),
          degree: pickEdu('input[name="education[][degree]"]'),
          start: pickEdu('input[name="education[][start]"]'),
          end: pickEdu('input[name="education[][end]"]'),
        };
      })
      .filter((entry) => Object.values(entry).some((value) => value));
  };

  const collectFormData = () => {
    const field = (selector) => {
      const element = builderForm.querySelector(selector);
      return element ? element.value.trim() : '';
    };

    return {
      job_title: field('input[name="job_title"]'),
      location: field('input[name="location"]'),
      first_name: field('input[name="first_name"]'),
      last_name: field('input[name="last_name"]'),
      email: field('input[name="email"]'),
      phone: field('input[name="phone"]'),
      summary: field('textarea[name="summary"]'),
      skills: field('textarea[name="skills"]'),
      employment: collectRepeatable('employment'),
      education: collectRepeatable('education'),
    };
  };

  const buildPlainTextResume = (data = {}) => {
    const sections = [];
    const name = [data.first_name, data.last_name].filter(Boolean).join(' ').trim();
    const jobTitle = (data.job_title || '').trim();
    const contact = [data.location, data.email, data.phone].map((item) => (item || '').trim()).filter(Boolean).join(' • ');
    if (name) {
      sections.push(name);
    }
    if (jobTitle) {
      sections.push(jobTitle);
    }
    if (contact) {
      sections.push(contact);
    }
    sections.push('');

    if (data.summary) {
      sections.push('SUMMARY');
      sections.push(data.summary);
      sections.push('');
    }

    if (Array.isArray(data.employment) && data.employment.length) {
      sections.push('EXPERIENCE');
      data.employment.forEach((role) => {
        const header = [role.title, role.company].filter(Boolean).join(' • ');
        const dates = [role.start, role.end].filter(Boolean).join(' – ');
        if (header) {
          sections.push(header);
        }
        if (dates) {
          sections.push(dates);
        }
        if (role.summary) {
          sections.push(role.summary);
        }
        sections.push('');
      });
    }

    if (Array.isArray(data.education) && data.education.length) {
      sections.push('EDUCATION');
      data.education.forEach((edu) => {
        const line = [edu.degree, edu.school].filter(Boolean).join(', ');
        const dates = [edu.start, edu.end].filter(Boolean).join(' – ');
        if (line) {
          sections.push(line);
        }
        if (dates) {
          sections.push(dates);
        }
        sections.push('');
      });
    }

    const skills = Array.isArray(data.skills)
      ? data.skills
      : (data.skills || '')
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean);

    if (skills.length) {
      sections.push('SKILLS');
      sections.push(skills.join(', '));
    }

    return sections.join('\n').trim();
  };

  const renderPreview = (rawData = {}, suggestions = {}) => {
    if (!previewEl) {
      return;
    }

    const merged = { ...rawData };
    const structured = suggestions && suggestions.sections ? suggestions.sections : suggestions;
    ['summary', 'employment', 'education', 'skills'].forEach((key) => {
      if (typeof structured[key] !== 'undefined' && structured[key] !== null) {
        merged[key] = structured[key];
      }
    });

    const firstName = (merged.first_name || '').trim();
    const lastName = (merged.last_name || '').trim();
    const hasName = firstName || lastName;
    const displayName = hasName ? `${firstName} ${lastName}`.trim() : 'Your Name';
    const jobTitle = (merged.job_title || '').trim();
    const contactParts = [merged.location, merged.email, merged.phone]
      .map((item) => (item || '').trim())
      .filter(Boolean);
    const summary = (merged.summary || '').trim();

    const normalizeArray = (items) => (Array.isArray(items) ? items : []);
    const employment = normalizeArray(merged.employment)
      .map((role) => ({
        title: (role.title || '').trim(),
        company: (role.company || '').trim(),
        start: (role.start || '').trim(),
        end: (role.end || '').trim(),
        summary: (role.summary || role.description || '').trim(),
      }))
      .filter((role) => role.title || role.company || role.summary);

    const education = normalizeArray(merged.education)
      .map((item) => ({
        school: (item.school || '').trim(),
        degree: (item.degree || '').trim(),
        start: (item.start || '').trim(),
        end: (item.end || '').trim(),
      }))
      .filter((item) => item.school || item.degree);

    const skillsArray = Array.isArray(merged.skills)
      ? merged.skills
      : (merged.skills || '').split(',').map((skill) => skill.trim()).filter(Boolean);

    const hasContent = hasName || jobTitle || contactParts.length || summary || employment.length || education.length || skillsArray.length;
    if (!hasContent) {
      previewEl.innerHTML = `<p class="builder-preview__placeholder">${fixResumeBuilder.labels.placeholder}</p>`;
      return;
    }

    const sections = [];
    sections.push(`
      <section class="preview-section preview-section__header">
        <h1>${escapeHtml(displayName)}</h1>
        ${jobTitle ? `<p class="preview-job">${escapeHtml(jobTitle)}</p>` : ''}
        ${contactParts.length ? `<p class="preview-contact">${escapeHtml(contactParts.join(' • '))}</p>` : ''}
      </section>
    `);

    if (summary) {
      sections.push(`
        <section class="preview-section">
          <h3>${escapeHtml(fixResumeBuilder.labels.summary)}</h3>
          <p>${formatMultiline(summary)}</p>
        </section>
      `);
    }

    if (employment.length) {
      const experienceLabel = fixResumeBuilder.labels.experience || 'Experience';
      const roles = employment.map((role) => {
        const meta = [role.title, role.company].filter(Boolean).map(escapeHtml).join(' • ');
        const dates = [role.start, role.end].filter(Boolean).map(escapeHtml).join(' – ');
        return `
          <article class="preview-role">
            ${meta ? `<p class="preview-role__meta"><strong>${meta}</strong></p>` : ''}
            ${dates ? `<p class="preview-role__dates">${dates}</p>` : ''}
            ${role.summary ? `<p>${formatMultiline(role.summary)}</p>` : ''}
          </article>
        `;
      }).join('');
      sections.push(`
        <section class="preview-section">
          <h3>${escapeHtml(experienceLabel)}</h3>
          ${roles}
        </section>
      `);
    }

    if (education.length) {
      const lines = education.map((item) => {
        const meta = [item.degree, item.school].filter(Boolean).map(escapeHtml).join(', ');
        const dates = [item.start, item.end].filter(Boolean).map(escapeHtml).join(' – ');
        return `
          <article class="preview-edu">
            ${meta ? `<p class="preview-edu__meta"><strong>${meta}</strong></p>` : ''}
            ${dates ? `<p class="preview-edu__dates">${dates}</p>` : ''}
          </article>
        `;
      }).join('');
      sections.push(`
        <section class="preview-section">
          <h3>${escapeHtml(fixResumeBuilder.labels.education)}</h3>
          ${lines}
        </section>
      `);
    }

    if (skillsArray.length) {
      sections.push(`
        <section class="preview-section">
          <h3>${escapeHtml(fixResumeBuilder.labels.skills)}</h3>
          <p>${escapeHtml(skillsArray.join(' • '))}</p>
        </section>
      `);
    }

    previewEl.innerHTML = sections.join('\n');
  };

  const updatePreviewAndDraft = (presetData) => {
    const snapshot = presetData || collectFormData();
    renderPreview(snapshot, state.lastResponse || {});
    persistDraft(snapshot);
    return snapshot;
  };

  const schedulePreviewUpdate = debounce(() => {
    updatePreviewAndDraft();
  }, 220);

  const invalidateAiResponse = () => {
    if (state.lastResponse) {
      state.lastResponse = null;
      updateScore();
      builderStorage.lastResponse = null;
      saveWizardState();
    }
  };

  const handleUserInput = () => {
    if (state.isHydrating) {
      return;
    }
    invalidateAiResponse();
    schedulePreviewUpdate();
  };

  builderForm.addEventListener('input', handleUserInput);
  builderForm.addEventListener('change', handleUserInput);

  builderForm.addEventListener('click', (event) => {
    const rewriteButton = event.target.closest('.builder-rewrite');
    if (rewriteButton) {
      event.preventDefault();
      const card = rewriteButton.closest('.repeatable-card');
      if (card) {
        rewriteEmploymentBullet(card);
      }
      return;
    }

    if (event.target.classList.contains('repeatable-remove')) {
      event.preventDefault();
      const card = event.target.closest('.repeatable-card');
      if (card) {
        card.remove();
        if (!state.isHydrating) {
          invalidateAiResponse();
          updatePreviewAndDraft();
        }
      }
    }
  });

  const defaultData = {
    job_title: 'Senior Growth Product Manager',
    location: 'Seattle, WA',
    first_name: 'Jordan',
    last_name: 'Lee',
    email: 'jordan.lee@example.com',
    phone: '(555) 019-8890',
    summary: 'Growth-focused PM with 8+ years scaling SaaS products. Specializes in GTM experiments, data storytelling, and cross-functional leadership.',
    skills: 'GTM strategy, Paid acquisition, Lifecycle automation, SQL, Stakeholder storytelling',
    employment: [
      {
        title: 'Product Marketing Manager',
        company: 'Northwind Apps',
        start: 'Jan 2021',
        end: 'Present',
        summary: 'Led GTM for 3 launches, lifting self-serve revenue 32%. Partnered with Sales to create enablement kits adopted by 120 reps.',
      },
      {
        title: 'Growth Strategist',
        company: 'Contoso Cloud',
        start: 'Feb 2017',
        end: 'Dec 2020',
        summary: 'Owned demand gen roadmap; launched referral engine that added $4M ARR. Managed a squad of 5 marketers.',
      },
    ],
    education: [
      {
        school: 'University of Colorado Boulder',
        degree: 'MBA, Marketing & Analytics',
        start: '2014',
        end: '2016',
      },
    ],
  };

  const assignFieldValue = (selector, value) => {
    const field = builderForm.querySelector(selector);
    if (field) {
      field.value = value || '';
    }
  };

  const populateForm = (data = {}) => {
    state.isHydrating = true;

    assignFieldValue('input[name="job_title"]', data.job_title);
    assignFieldValue('input[name="location"]', data.location);
    assignFieldValue('input[name="first_name"]', data.first_name);
    assignFieldValue('input[name="last_name"]', data.last_name);
    assignFieldValue('input[name="email"]', data.email);
    assignFieldValue('input[name="phone"]', data.phone);
    assignFieldValue('textarea[name="summary"]', data.summary);
    assignFieldValue('textarea[name="skills"]', Array.isArray(data.skills) ? data.skills.join(', ') : data.skills);

    Object.values(repeaterConfig).forEach((config) => {
      if (config && config.container) {
        config.container.innerHTML = '';
      }
    });

    const employmentRows = Array.isArray(data.employment) && data.employment.length ? data.employment : [{}];
    employmentRows.forEach((item) => addRepeaterItem('employment', item));

    const educationRows = Array.isArray(data.education) && data.education.length ? data.education : [{}];
    educationRows.forEach((item) => addRepeaterItem('education', item));

    state.isHydrating = false;
  };

  const restoreDraft = () => {
    if (!builderStorage.draft) {
      return false;
    }
    populateForm(builderStorage.draft);
    updatePreviewAndDraft(builderStorage.draft);
    return true;
  };

  const applyUserPrefill = () => {
    if (!userPrefill || typeof userPrefill !== 'object') {
      return;
    }

    Object.entries(userPrefill).forEach(([key, value]) => {
      if (!value) {
        return;
      }
      assignFieldValue(`[name="${key}"]`, value);
    });
  };

  const fillDefaults = () => {
    const mergedDefaults = { ...defaultData, ...userPrefill };
    populateForm(mergedDefaults);
    updateScore();
    renderPreview(mergedDefaults);
  };

  if (!restoreDraft()) {
    fillDefaults();
  } else {
    applyUserPrefill();
  }

  const resetForm = () => {
    state.isHydrating = true;
    builderForm.reset();
    Object.values(repeaterConfig).forEach((config) => {
      if (config && config.container) {
        config.container.innerHTML = '';
      }
    });
    addRepeaterItem('employment');
    addRepeaterItem('education');
    state.isHydrating = false;
    state.lastResponse = null;
    builderStorage.lastResponse = null;
    updateScore();
    removeDraft();
    applyUserPrefill();
    renderPreview();
    showStep(1);
    saveWizardState();
  };

  if (resetBtn) {
    resetBtn.addEventListener('click', (event) => {
      event.preventDefault();
      resetForm();
    });
  }

  const rewriteEmploymentBullet = async (card) => {
    if (!card || !repeaterConfig.employment || !repeaterConfig.employment.container) {
      return;
    }

    const summaryField = card.querySelector('textarea[name="employment[][summary]"]');
    if (!summaryField || !summaryField.value.trim()) {
      showWarning(fixResumeBuilder.messages.bulletMissing || 'Add a bullet before rewriting it.');
      return;
    }

    const cards = Array.from(repeaterConfig.employment.container.querySelectorAll('.repeatable-card'));
    const index = cards.indexOf(card);
    if (index < 0) {
      showWarning(fixResumeBuilder.messages.bulletError || 'Unable to rewrite this bullet right now.');
      return;
    }

    const payload = collectFormData();
    const requestBody = {
      ...payload,
      ai_mode: 'bullet',
      target_bullet: {
        index,
        summary: summaryField.value.trim(),
        title: (card.querySelector('input[name="employment[][title]"]') || {}).value || '',
        company: (card.querySelector('input[name="employment[][company]"]') || {}).value || '',
      },
    };

    showLoading();
    try {
      const response = await fetch(fixResumeBuilder.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      const json = await response.json();
      if (!response.ok || !json?.success || !json?.data?.bullet) {
        throw new Error(json?.error || fixResumeBuilder.messages.bulletError);
      }

      summaryField.value = json.data.bullet;
      invalidateAiResponse();
      updatePreviewAndDraft();

      showModal({
        icon: 'success',
        title: fixResumeBuilder.messages.bulletSuccess || 'Bullet rewritten.',
        timer: 1400,
        showConfirmButton: false,
      });
    } catch (error) {
      console.error(error);
      showError(error.message || fixResumeBuilder.messages.bulletError);
    } finally {
      hideLoading();
    }
  };

  const getExportDocument = () => {
    if (state.lastResponse && state.lastResponse.resume_document) {
      return state.lastResponse.resume_document;
    }
    return buildPlainTextResume(collectFormData());
  };

  const copyPreview = async () => {
    const documentText = getExportDocument();
    if (!documentText) {
      showWarning(fixResumeBuilder.messages.generateFirst || 'Please add resume content first.');
      return;
    }
    try {
      await navigator.clipboard.writeText(documentText);
      showModal({ icon: 'success', title: fixResumeBuilder.messages.copy });
    } catch (error) {
      console.error(error);
      showError(fixResumeBuilder.messages.copyFail);
    }
  };

  if (copyBtn) {
    copyBtn.addEventListener('click', copyPreview);
  }

  if (summaryAiBtn) {
    summaryAiBtn.addEventListener('click', (event) => {
      event.preventDefault();
      improveSummaryWithAi();
    });
  }

  const openUnlockModal = () => {
    if (unlockModal) {
      unlockModal.classList.add('is-open');
    } else if (window.fixResumePaywall?.redirectToPricing) {
      window.fixResumePaywall.redirectToPricing();
    } else if (pricingUrl) {
      window.location.href = pricingUrl;
    }
  };

  const closeUnlockModal = () => {
    if (unlockModal) {
      unlockModal.classList.remove('is-open');
    }
  };

  document.querySelectorAll('[data-unlock-close]').forEach((button) => {
    button.addEventListener('click', closeUnlockModal);
  });

  document.querySelectorAll('[data-unlock-start]').forEach((button) => {
    button.addEventListener('click', () => {
      try {
        sessionStorage.setItem('rai_return', window.location.href);
      } catch (error) {
        console.warn(error);
      }

      closeUnlockModal();
      if (window.fixResumePaywall && typeof window.fixResumePaywall.startCheckout === 'function') {
        window.fixResumePaywall.startCheckout(button.dataset.plan || 'primary');
      }
    });
  });

  const base64ToBlob = (encoded, mimeType = 'application/octet-stream') => {
    const binary = atob(encoded);
    const length = binary.length;
    const buffer = new Uint8Array(length);
    for (let i = 0; i < length; i += 1) {
      buffer[i] = binary.charCodeAt(i);
    }
    return new Blob([buffer], { type: mimeType });
  };

  const downloadBinaryFile = (fileData = {}) => {
    if (!fileData.file) {
      return;
    }
    const blob = base64ToBlob(fileData.file, fileData.mime_type || 'application/octet-stream');
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileData.filename || 'resume-download';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const downloadTextFallback = () => {
    const documentText = getExportDocument();
    if (!documentText) {
      showWarning(fixResumeBuilder.messages.generateFirst || 'Please add resume content first.');
      return;
    }
    const blob = new Blob([documentText.trim()], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'resume.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const downloadPreview = async (format = 'pdf') => {
    const normalizedFormat = format === 'docx' ? 'docx' : 'pdf';
    if (!canDownload) {
      openUnlockModal();
      return;
    }

    if (!fixResumeBuilder.exportEndpoint) {
      showWarning(fixResumeBuilder.messages.exportError || 'We could not build the download.');
      downloadTextFallback();
      return;
    }

    showLoading();

    try {
      const payload = collectFormData();
      const response = await fetch(fixResumeBuilder.exportEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type: 'builder',
          data: payload,
          filename: normalizedFormat === 'docx' ? 'resume.docx' : 'resume.pdf',
          format: normalizedFormat,
        }),
      });

      if (response.status === 401 || response.status === 403) {
        openUnlockModal();
        return;
      }

      const json = await response.json();
      if (!response.ok || !json?.success || !json?.data?.file) {
        throw new Error(json?.error || fixResumeBuilder.messages.exportError || 'Unable to export resume.');
      }

      downloadBinaryFile(json.data);

      if (fixResumeBuilder.messages.exportSuccess) {
        showModal({ icon: 'success', title: fixResumeBuilder.messages.exportSuccess, timer: 1400, showConfirmButton: false });
      }
    } catch (error) {
      console.error(error);
      showWarning(error.message || fixResumeBuilder.messages.exportError || 'Unable to export resume. Downloading text version instead.');
      downloadTextFallback();
    } finally {
      hideLoading();
    }
  };

  if (downloadButtons.length) {
    downloadButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const format = (button.dataset.downloadFormat || 'pdf').toLowerCase();
        downloadPreview(format);
      });
    });
  }

  const improveSummaryWithAi = async () => {
    const payload = collectFormData();
    if (!payload.summary) {
      showWarning(fixResumeBuilder.messages.requireSummary || 'Add a draft summary before improving it.');
      return;
    }

    showLoading();
    try {
      const response = await fetch(fixResumeBuilder.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ...payload, ai_mode: 'summary' }),
      });
      const json = await response.json();
      if (!response.ok || !json?.success) {
        throw new Error(json?.error || fixResumeBuilder.messages.error);
      }
      const summarySection = json.data?.sections?.summary || json.data?.summary;
      if (summarySection) {
        const summaryField = builderForm.querySelector('textarea[name="summary"]');
        if (summaryField) {
          summaryField.value = summarySection;
        }
        updatePreviewAndDraft({ ...payload, summary: summarySection });
      }
      showSuccess();
    } catch (error) {
      console.error(error);
      showError(error.message || fixResumeBuilder.messages.error);
    } finally {
      hideLoading();
    }
  };

  const applyAiResult = (payload) => {
    if (!payload) {
      return;
    }

    state.lastResponse = payload;
    builderStorage.lastResponse = payload;
    saveWizardState();
    updateScore(payload.score);
    const structured = payload.sections && typeof payload.sections === 'object' ? payload.sections : payload;
    const current = collectFormData();
    const merged = {
      ...current,
      summary: typeof structured.summary === 'string' ? structured.summary : current.summary,
      skills: structured.skills || current.skills,
      employment: Array.isArray(structured.employment) ? structured.employment : current.employment,
      education: Array.isArray(structured.education) ? structured.education : current.education,
    };

    populateForm(merged);
    updatePreviewAndDraft(merged);
  };

  builderForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = collectFormData();

    if (!payload.first_name || !payload.last_name) {
      showWarning(fixResumeBuilder.messages.requireName || 'Please provide at least your first and last name.');
      return;
    }

    showLoading();

    try {
      const response = await fetch(fixResumeBuilder.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await response.json();
      if (!response.ok || !json?.success) {
        throw new Error(json?.error || 'Unable to generate resume.');
      }

      applyAiResult(json.data);
      showSuccess();
    } catch (error) {
      console.error(error);
      showError(error.message || fixResumeBuilder.messages.error);
    } finally {
      hideLoading();
    }
  });

  if (!state.isHydrating) {
    renderPreview(collectFormData());
  }
})();
