// ============================================================
// EduSchedule Pro – Main JavaScript
// ============================================================

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Auto-dismiss flash messages
window.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flash-msg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity .5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }

    // Initialize dependent dropdowns
    initDependentDropdowns();
});

// ============================================================
// Dependent Dropdowns
// ============================================================
function initDependentDropdowns() {
    // Sections filter: course → update section list via AJAX
    const filterCourse = document.getElementById('filter_course_id');
    if (filterCourse) {
        filterCourse.addEventListener('change', function () {
            updateSectionDropdown(this.value, 'filter_section_id', '', true);
        });
    }

    // Schedule form: course → sections → subjects
    const schedCourse = document.getElementById('form_course_id');
    if (schedCourse) {
        schedCourse.addEventListener('change', function () {
            updateSectionDropdown(this.value, 'form_section_id', '', false);
            document.getElementById('form_subject_id').innerHTML = '<option value="">-- Select Subject --</option>';
        });
    }

    const schedSection = document.getElementById('form_section_id');
    if (schedSection) {
        schedSection.addEventListener('change', function () {
            const courseId = document.getElementById('form_course_id')?.value;
            const sem = document.getElementById('form_semester')?.value;
            const secEl = document.getElementById('form_section_id');
            const secId = secEl ? secEl.options[secEl.selectedIndex] : null;
            const yearLevel = secId ? secId.getAttribute('data-year') : '';
            updateSubjectDropdown(courseId, yearLevel, sem, 'form_subject_id', '');
        });
    }

    const schedSem = document.getElementById('form_semester');
    if (schedSem) {
        schedSem.addEventListener('change', function () {
            const courseId = document.getElementById('form_course_id')?.value;
            const secEl = document.getElementById('form_section_id');
            const secId = secEl ? secEl.options[secEl.selectedIndex] : null;
            const yearLevel = secId ? secId.getAttribute('data-year') : '';
            updateSubjectDropdown(courseId, yearLevel, this.value, 'form_subject_id', '');
        });
    }
}

function updateSectionDropdown(courseId, targetId, selectedVal, addAll) {
    const el = document.getElementById(targetId);
    if (!el) return;
    el.innerHTML = '<option value="">Loading...</option>';
    fetch(`api.php?action=get_sections&course_id=${encodeURIComponent(courseId)}`)
        .then(r => r.json())
        .then(data => {
            el.innerHTML = addAll ? '<option value="">All Sections</option>' : '<option value="">-- Select Section --</option>';
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name + ' (' + s.year_level + ')';
                opt.setAttribute('data-year', s.year_level);
                if (s.id == selectedVal) opt.selected = true;
                el.appendChild(opt);
            });
        });
}

function updateSubjectDropdown(courseId, yearLevel, semester, targetId, selectedVal) {
    const el = document.getElementById(targetId);
    if (!el) return;
    el.innerHTML = '<option value="">Loading...</option>';
    fetch(`api.php?action=get_subjects&course_id=${encodeURIComponent(courseId)}&year_level=${encodeURIComponent(yearLevel)}&semester=${encodeURIComponent(semester)}`)
        .then(r => r.json())
        .then(data => {
            el.innerHTML = '<option value="">-- Select Subject --</option>';
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.code + ' – ' + s.name;
                if (s.id == selectedVal) opt.selected = true;
                el.appendChild(opt);
            });
        });
}

// Timetable: course → sections
function ttUpdateSections() {
    const courseId = document.getElementById('tt_course_id')?.value;
    updateSectionDropdown(courseId, 'tt_section_id', '', false);
}

// Confirm delete
function confirmDelete(form) {
    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        return false;
    }
    return true;
}

// Print timetable
function printTimetable() {
    window.print();
}

// Export CSV
function exportCSV(url) {
    window.location.href = url;
}
