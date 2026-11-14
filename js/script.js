document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const currentPage = body.dataset.page;

  // Active nav state
  const navLinks = document.querySelectorAll('.nav-link');
  navLinks.forEach((link) => {
    const pageMatch = link.textContent.trim().toLowerCase();
    if (currentPage === 'index' && link.getAttribute('href') === 'index.html') {
      link.classList.add('active');
    }
  });

  const pathname = window.location.pathname.split('/').pop() || 'index.html';
  const activeNavTargets = {
    index: 'index.html',
    about: 'about.html',
    courses: 'courses.html',
    admissions: 'admissions.html',
    contact: 'contact.html',
  };

  document.querySelectorAll('.nav-link').forEach((link) => {
    const url = link.getAttribute('href');
    if (url === activeNavTargets[currentPage]) {
      link.classList.add('active');
    }
  });

  // Mobile navigation toggle
  const navToggle = document.querySelector('.nav-toggle');
  const navMenu = document.querySelector('.nav-menu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      navMenu.classList.toggle('open');
      navToggle.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', String(navMenu.classList.contains('open')));
    });
  }

  // Desktop dropdowns and mobile submenu toggle
  const dropdownItems = document.querySelectorAll('.has-dropdown');
  dropdownItems.forEach((item) => {
    const toggle = item.querySelector('.dropdown-toggle');
    const menu = item.querySelector('.dropdown-menu');

    if (!toggle || !menu) return;

    const isMobile = () => window.innerWidth <= 900;

    toggle.addEventListener('click', (event) => {
      if (isMobile()) {
        event.preventDefault();
        const isOpen = item.classList.contains('open');
        dropdownItems.forEach((dropdown) => {
          if (dropdown !== item) {
            dropdown.classList.remove('open');
            const otherToggle = dropdown.querySelector('.dropdown-toggle');
            if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
          }
        });

        item.classList.toggle('open', !isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
      } else {
        const open = item.classList.contains('open');
        dropdownItems.forEach((dropdown) => {
          if (dropdown !== item) {
            dropdown.classList.remove('open');
            const otherToggle = dropdown.querySelector('.dropdown-toggle');
            if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
          }
        });
        item.classList.toggle('open', !open);
        toggle.setAttribute('aria-expanded', String(!open));
      }
    });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.has-dropdown')) {
      dropdownItems.forEach((item) => {
        item.classList.remove('open');
        const toggle = item.querySelector('.dropdown-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
    }
  });

  // Sticky nav shadow enhancement
  const header = document.querySelector('.site-header');
  const updateHeaderState = () => {
    if (!header) return;
    if (window.scrollY > 20) {
      header.style.boxShadow = '0 10px 30px rgba(38, 51, 42, 0.05)';
    } else {
      header.style.boxShadow = 'none';
    }
  };
  updateHeaderState();
  window.addEventListener('scroll', updateHeaderState);

  // Scroll reveal
  const revealItems = document.querySelectorAll('.reveal');
  if (revealItems.length) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });

    revealItems.forEach((item) => revealObserver.observe(item));
  }

  // Back to top button
  const backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    const toggleBackToTop = () => {
      if (window.scrollY > 400) {
        backToTop.classList.add('visible');
      } else {
        backToTop.classList.remove('visible');
      }
    };

    toggleBackToTop();
    window.addEventListener('scroll', toggleBackToTop);
    backToTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Counter animation
  const counters = document.querySelectorAll('.counter');
  if (counters.length) {
    const animateCounter = (element) => {
      const targetValue = Number(element.dataset.target || 0);
      const suffix = element.textContent.includes('%') ? '%' : '';
      const duration = 1200;
      const start = performance.now();

      const tick = (currentTime) => {
        const progress = Math.min((currentTime - start) / duration, 1);
        const value = Math.ceil(progress * targetValue);
        element.textContent = `${value}${suffix}`;
        if (progress < 1) {
          requestAnimationFrame(tick);
        } else {
          element.textContent = `${targetValue}${suffix}`;
        }
      };

      requestAnimationFrame(tick);
    };

    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach((counter) => counterObserver.observe(counter));
  }

  // Testimonial slider
  const testimonialItems = document.querySelectorAll('.testimonial-item');
  const dots = document.querySelectorAll('.dot');
  const prevBtn = document.querySelector('.slider-btn.prev');
  const nextBtn = document.querySelector('.slider-btn.next');
  let currentSlideIndex = 0;

  function updateTestimonials(index) {
    if (!testimonialItems.length) return;

    currentSlideIndex = (index + testimonialItems.length) % testimonialItems.length;
    testimonialItems.forEach((item, itemIndex) => {
      item.classList.toggle('active', itemIndex === currentSlideIndex);
    });

    dots.forEach((dot, dotIndex) => {
      dot.classList.toggle('active', dotIndex === currentSlideIndex);
    });
  }

  if (testimonialItems.length) {
    prevBtn?.addEventListener('click', () => updateTestimonials(currentSlideIndex - 1));
    nextBtn?.addEventListener('click', () => updateTestimonials(currentSlideIndex + 1));
    dots.forEach((dot, index) => {
      dot.addEventListener('click', () => updateTestimonials(index));
    });
    setInterval(() => updateTestimonials(currentSlideIndex + 1), 5500);
  }

  // Campus gallery lightbox
  const campusItems = [...document.querySelectorAll('.campus-gallery-item')];
  const campusLightbox = document.getElementById('campusLightbox');

  if (campusItems.length && campusLightbox) {
    const campusImage = campusLightbox.querySelector('.campus-lightbox-image');
    const campusCaption = campusLightbox.querySelector('.campus-lightbox-caption');
    const campusPrevious = campusLightbox.querySelector('.campus-lightbox-prev');
    const campusNext = campusLightbox.querySelector('.campus-lightbox-next');
    let currentCampusIndex = 0;
    let previousFocus = null;

    const showCampusImage = (index) => {
      currentCampusIndex = (index + campusItems.length) % campusItems.length;
      const item = campusItems[currentCampusIndex];
      const image = item.querySelector('img');
      const caption = item.querySelector('.campus-gallery-caption');

      if (!image || !caption || !campusImage || !campusCaption) return;
      campusImage.src = image.src;
      campusImage.alt = image.alt;
      campusCaption.textContent = caption.textContent;
    };

    const closeCampusLightbox = () => {
      campusLightbox.classList.remove('open');
      campusLightbox.setAttribute('aria-hidden', 'true');
      body.classList.remove('campus-lightbox-open');
      previousFocus?.focus();
    };

    campusItems.forEach((item, index) => {
      item.addEventListener('click', () => {
        previousFocus = item;
        showCampusImage(index);
        campusLightbox.classList.add('open');
        campusLightbox.setAttribute('aria-hidden', 'false');
        body.classList.add('campus-lightbox-open');
        campusLightbox.querySelector('.campus-lightbox-close')?.focus();
      });
    });

    campusPrevious?.addEventListener('click', () => showCampusImage(currentCampusIndex - 1));
    campusNext?.addEventListener('click', () => showCampusImage(currentCampusIndex + 1));
    campusLightbox.querySelectorAll('[data-campus-close]').forEach((element) => {
      element.addEventListener('click', closeCampusLightbox);
    });

    document.addEventListener('keydown', (event) => {
      if (!campusLightbox.classList.contains('open')) return;
      if (event.key === 'Escape') closeCampusLightbox();
      if (event.key === 'ArrowLeft') showCampusImage(currentCampusIndex - 1);
      if (event.key === 'ArrowRight') showCampusImage(currentCampusIndex + 1);
    });
  }

  // FAQ accordion
  const faqItems = document.querySelectorAll('.faq-item');
  FAQ: {
    faqItems.forEach((item) => {
      const button = item.querySelector('.faq-question');
      if (!button) return;

      button.addEventListener('click', () => {
        const isOpen = item.classList.contains('active');

        faqItems.forEach((faqItem) => {
          faqItem.classList.remove('active');
          const faqButton = faqItem.querySelector('.faq-question');
          if (faqButton) faqButton.setAttribute('aria-expanded', 'false');
        });

        if (!isOpen) {
          item.classList.add('active');
          button.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  // Course filtering
  const filterButtons = document.querySelectorAll('.filter-btn');
  const courseCards = document.querySelectorAll('.course-card');

  if (filterButtons.length && courseCards.length) {
    filterButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const filter = button.dataset.filter;
        filterButtons.forEach((btn) => btn.classList.toggle('active', btn === button));

        courseCards.forEach((card) => {
          const category = card.dataset.category;
          const shouldShow = filter === 'all' || filter === category;
          card.classList.toggle('hidden', !shouldShow);
        });
      });
    });
  }

  // Course modal data
  const courseDetails = {
    'diploma-it': {
      title: 'Diploma in Information Technology',
      category: 'IT',
      duration: '12 Months',
      overview: 'A practical and career-focused program designed to build strong foundations in information technology, software tools, and digital systems.',
      learn: ['Programming fundamentals', 'Database management', 'Web technologies', 'Problem solving and system analysis'],
      outcomes: ['Apply technical knowledge in real projects', 'Build confidence in digital tools and systems', 'Prepare for further study or entry-level roles in IT']
    },
    'web-dev': {
      title: 'Professional Web Development',
      category: 'Professional',
      duration: '06 Months',
      overview: 'This intensive course helps learners build practical web development knowledge through structured lessons and hands-on assignments.',
      learn: ['HTML and CSS layout techniques', 'JavaScript fundamentals', 'Responsive design', 'Web project implementation'],
      outcomes: ['Create professional web interfaces', 'Understand modern frontend techniques', 'Develop an employable portfolio of work']
    },
    'software-fundamentals': {
      title: 'Software Development Fundamentals',
      category: 'IT',
      duration: '08 Months',
      overview: 'A foundational pathway for learners seeking to understand how software is designed, developed, and tested in the modern workplace.',
      learn: ['Programming logic', 'Structured coding', 'Application design', 'Testing and debugging'],
      outcomes: ['Write clean, logical code', 'Understand software workflows', 'Prepare for advanced technical study']
    },
    'business-management': {
      title: 'Diploma in Business Management',
      category: 'Business',
      duration: '12 Months',
      overview: 'Designed for learners who want to gain a practical understanding of business functions, leadership, and operational excellence.',
      learn: ['Management principles', 'Business communication', 'Operations and planning', 'Leadership and organizational skills'],
      outcomes: ['Understand core business systems', 'Speak confidently in professional settings', 'Support organizational goals and teamwork']
    },
    'business-admin': {
      title: 'Business Administration Essentials',
      category: 'Business',
      duration: '09 Months',
      overview: 'This course supports aspiring professionals with the foundational knowledge needed for business operations and administrative excellence.',
      learn: ['Business administration systems', 'Decision-making and planning', 'Effective communication', 'Customer and service orientation'],
      outcomes: ['Manage core administrative responsibilities', 'Support business processes', 'Build a strong foundation for business careers']
    },
    'graphic-design': {
      title: 'Graphic Design Essentials',
      category: 'Short Courses',
      duration: '03 Months',
      overview: 'Create strong visual communication skills through design principles, branding ideas, and digital creativity.',
      learn: ['Design fundamentals', 'Typography and layout', 'Creative thinking', 'Digital design tools'],
      outcomes: ['Create visually appealing content', 'Apply design principles effectively', 'Build a confident creative portfolio']
    },
    'digital-marketing-fundamentals': {
      title: 'Digital Marketing Fundamentals',
      category: 'Short Courses',
      duration: '04 Months',
      overview: 'A practical introduction to online marketing, strategy, content, and audience engagement for modern brands.',
      learn: ['Content strategy', 'Social media marketing', 'Campaign planning', 'Performance fundamentals'],
      outcomes: ['Understand digital marketing channels', 'Plan content for business goals', 'Support promotional initiatives confidently']
    },
    'communication-skills': {
      title: 'Professional Communication & Career Skills',
      category: 'Professional',
      duration: '06 Weeks',
      overview: 'Develop practical communication, presentation, and career readiness skills for personal and professional advancement.',
      learn: ['Presentation delivery', 'Interview techniques', 'Professional communication', 'Career planning'],
      outcomes: ['Communicate with confidence', 'Prepare for interviews and workplace settings', 'Strengthen professional presence']
    }
  };

  const modal = document.getElementById('courseModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalCategory = document.getElementById('modalCategory');
  const modalDuration = document.getElementById('modalDuration');
  const modalOverview = document.getElementById('modalOverview');
  const modalLearn = document.getElementById('modalLearn');
  const modalOutcomes = document.getElementById('modalOutcomes');

  const openModal = (courseId) => {
    if (!modal || !courseDetails[courseId]) return;
    const detail = courseDetails[courseId];
    modalTitle.textContent = detail.title;
    modalCategory.textContent = detail.category;
    modalDuration.textContent = `Duration: ${detail.duration}`;
    modalOverview.textContent = detail.overview;
    modalLearn.innerHTML = detail.learn.map((item) => `<li>${item}</li>`).join('');
    modalOutcomes.innerHTML = detail.outcomes.map((item) => `<li>${item}</li>`).join('');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  };

  document.querySelectorAll('.view-details').forEach((button) => {
    button.addEventListener('click', () => openModal(button.dataset.courseId));
  });

  document.querySelector('[data-close="modal"]')?.addEventListener('click', closeModal);
  document.querySelector('.modal-close')?.addEventListener('click', closeModal);
  document.querySelector('.close-modal')?.addEventListener('click', closeModal);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && modal.classList.contains('open')) {
      closeModal();
    }
  });

  // Form validation
  const validateTextField = (value) => value.trim().length > 1;
  const validateEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
  const validatePhone = (value) => /^[+]?\d[\d\s()-]{7,}$/.test(value.trim());

  const showSuccess = (form, message) => {
    const successBox = form.querySelector('.form-success');
    if (!successBox) return;
    successBox.textContent = message;
    successBox.classList.remove('error');
  };

  const showError = (form, message) => {
    const successBox = form.querySelector('.form-success');
    if (!successBox) return;
    successBox.textContent = message;
    successBox.classList.add('error');
  };

  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    const tokenField = document.getElementById('contact-csrf-token');
    fetch('contact-token.php', { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) throw new Error('Unable to prepare the contact form.');
        return response.json();
      })
      .then((data) => {
        if (tokenField && typeof data.token === 'string') tokenField.value = data.token;
      })
      .catch(() => {
        showError(contactForm, 'The secure form could not be prepared. Please reload the page and try again.');
      });

    contactForm.addEventListener('submit', (event) => {
      const name = document.getElementById('contact-name');
      const email = document.getElementById('contact-email');
      const subject = document.getElementById('subject');
      const messageBox = document.getElementById('message-box');

      if (!validateTextField(name.value) || !validateEmail(email.value) || !validateTextField(subject.value) || !validateTextField(messageBox.value)) {
        event.preventDefault();
        showError(contactForm, 'Please complete all fields with valid information before sending your message.');
        return;
      }

      if (!tokenField || !tokenField.value) {
        event.preventDefault();
        showError(contactForm, 'The secure form is still loading. Please wait a moment and try again.');
      }
    });

    const contactStatus = new URLSearchParams(window.location.search).get('contact');
    const messages = {
      sent: ['Message sent successfully. Thank you for contacting Nexora Institute.', false],
      invalid: ['Please reload the page and submit valid contact details.', true],
      wait: ['Please wait 30 seconds before sending another message.', true]
    };
    if (contactStatus && messages[contactStatus]) {
      const [message, isError] = messages[contactStatus];
      isError ? showError(contactForm, message) : showSuccess(contactForm, message);
      window.history.replaceState(null, '', window.location.pathname + window.location.hash);
    }
  }

  // Prevent mobile nav when clicking nav links on small screens
  if (navMenu) {
    navMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) {
          navMenu.classList.remove('open');
          navToggle?.classList.remove('open');
        }
      });
    });
  }
});
