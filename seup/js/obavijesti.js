let obavijestiBellDropdownOpen = false;
let obavijestiBellCheckInterval = null;
let obavijestiBellLastCount = 0;
let obavijestiBellCurrentSlide = 0;
let obavijestiBellSliderObavijesti = [];

function obavijestiBellInit() {
    obavijestiBellLoadData();
    obavijestiBellStartAutoRefresh();
    obavijestiBellAttachListeners();
}

function obavijestiBellStartAutoRefresh() {
    if (obavijestiBellCheckInterval) {
        clearInterval(obavijestiBellCheckInterval);
    }

    obavijestiBellCheckInterval = setInterval(function() {
        obavijestiBellLoadData();
    }, 60000);
}

function obavijestiBellLoadData() {
    fetch(window.location.origin + '/seup/class/autocomplete.php?action=get_obavijesti')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                obavijestiBellUpdateBell(data.data);
                obavijestiBellUpdateSlider(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading obavijesti:', error);
        });
}

function obavijestiBellUpdateBell(obavijesti) {
    const bellIcon = document.querySelector('.obavijesti-bell-icon');
    const bellBadge = document.querySelector('.obavijesti-bell-badge');

    const neprocitane = obavijesti.filter(o => !o.procitana);
    const count = neprocitane.length;

    if (count > 0) {
        bellIcon.classList.add('has-unread');
        bellBadge.textContent = count;
        bellBadge.style.display = 'flex';

        if (count > obavijestiBellLastCount) {
            obavijestiBellPlaySound();
        }
    } else {
        bellIcon.classList.remove('has-unread');
        bellBadge.style.display = 'none';
    }

    obavijestiBellLastCount = count;
}

function obavijestiBellUpdateDropdown(obavijesti) {
    const dropdownBody = document.querySelector('.obavijesti-dropdown-body');

    if (!dropdownBody) {
        return;
    }

    dropdownBody.innerHTML = '';

    if (obavijesti.length === 0) {
        dropdownBody.innerHTML = `
            <div class="obavijesti-empty">
                <i class="fa fa-bell-slash"></i>
                <p>Nema obavijesti</p>
            </div>
        `;
        return;
    }

    obavijesti.forEach(obavijest => {
        const item = document.createElement('div');
        item.className = 'obavijesti-item' + (!obavijest.procitana ? ' unread' : '');
        item.dataset.id = obavijest.rowid;

        let badgeClass = 'info';
        if (obavijest.tip === 'upozorenje') badgeClass = 'upozorenje';
        if (obavijest.tip === 'tutorial') badgeClass = 'tutorial';

        let linkHtml = '';
        if (obavijest.vanjski_link) {
            linkHtml = `
                <a href="${obavijest.vanjski_link}"
                   target="_blank"
                   rel="noopener"
                   class="obavijesti-item-link"
                   onclick="event.stopPropagation();">
                    Otvori <i class="fa fa-external-link"></i>
                </a>
            `;
        }

        item.innerHTML = `
            <div class="obavijesti-item-header">
                <span class="obavijesti-item-title">${obavijest.naslov}</span>
                <span class="obavijesti-item-badge ${badgeClass}">${obavijest.tip}</span>
            </div>
            <div class="obavijesti-item-content">${obavijest.sadrzaj}</div>
            <div class="obavijesti-item-footer">
                <span class="obavijesti-item-date">${obavijest.datum_kreiranja}</span>
                ${linkHtml}
            </div>
        `;

        item.addEventListener('click', function() {
            obavijestiBellMarkAsRead(obavijest.rowid);
        });

        dropdownBody.appendChild(item);
    });
}

function obavijestiBellUpdateSlider(obavijesti) {
    obavijestiBellSliderObavijesti = obavijesti;

    const sliderContainer = document.querySelector('.obavijesti-slider');

    if (!sliderContainer) {
        return;
    }

    if (obavijesti.length === 0) {
        sliderContainer.style.display = 'none';
        return;
    }

    sliderContainer.style.display = 'block';
    obavijestiBellRenderSlide(obavijestiBellCurrentSlide);
}

function obavijestiBellRenderSlide(index) {
    const sliderContent = document.querySelector('.obavijesti-slider-content');

    if (!sliderContent || obavijestiBellSliderObavijesti.length === 0) {
        return;
    }

    const obavijest = obavijestiBellSliderObavijesti[index];

    let linkHtml = '';
    if (obavijest.vanjski_link) {
        linkHtml = `
            <a href="${obavijest.vanjski_link}"
               target="_blank"
               rel="noopener"
               class="obavijesti-slider-link">
                Pogledaj tutorial <i class="fa fa-external-link"></i>
            </a>
        `;
    }

    let dotsHtml = '';
    if (obavijestiBellSliderObavijesti.length > 1) {
        dotsHtml = '<div class="obavijesti-slider-dots">';
        obavijestiBellSliderObavijesti.forEach((_, i) => {
            dotsHtml += `<span class="obavijesti-slider-dot ${i === index ? 'active' : ''}" onclick="obavijestiBellGoToSlide(${i})"></span>`;
        });
        dotsHtml += '</div>';
    }

    let navBtns = '';
    if (obavijestiBellSliderObavijesti.length > 1) {
        navBtns = `
            <button class="obavijesti-slider-nav-btn" onclick="obavijestiBellPrevSlide()">
                <i class="fa fa-chevron-left"></i>
            </button>
            ${dotsHtml}
            <button class="obavijesti-slider-nav-btn" onclick="obavijestiBellNextSlide()">
                <i class="fa fa-chevron-right"></i>
            </button>
        `;
    }

    sliderContent.innerHTML = `
        <div class="obavijesti-slider-header">
            <h3 class="obavijesti-slider-title">
                <i class="fa fa-bell"></i>
                ${obavijest.naslov}
            </h3>
            <button class="obavijesti-slider-close" onclick="obavijestiBellCloseSlider(${obavijest.rowid})">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div class="obavijesti-slider-body">
            ${obavijest.sadrzaj}
        </div>
        <div class="obavijesti-slider-footer">
            <span class="obavijesti-slider-date">${obavijest.datum_kreiranja}</span>
            <div class="obavijesti-slider-navigation">
                ${navBtns}
                ${linkHtml}
            </div>
        </div>
    `;
}

function obavijestiBellNextSlide() {
    obavijestiBellCurrentSlide = (obavijestiBellCurrentSlide + 1) % obavijestiBellSliderObavijesti.length;
    obavijestiBellRenderSlide(obavijestiBellCurrentSlide);
}

function obavijestiBellPrevSlide() {
    obavijestiBellCurrentSlide = (obavijestiBellCurrentSlide - 1 + obavijestiBellSliderObavijesti.length) % obavijestiBellSliderObavijesti.length;
    obavijestiBellRenderSlide(obavijestiBellCurrentSlide);
}

function obavijestiBellGoToSlide(index) {
    obavijestiBellCurrentSlide = index;
    obavijestiBellRenderSlide(obavijestiBellCurrentSlide);
}

function obavijestiBellCloseSlider(obavijestId) {
    obavijestiBellMarkAsRead(obavijestId);
    document.querySelector('.obavijesti-slider').style.display = 'none';
}

function obavijestiBellToggleDropdown() {
    const dropdown = document.querySelector('.obavijesti-dropdown');

    if (!dropdown) {
        return;
    }

    obavijestiBellDropdownOpen = !obavijestiBellDropdownOpen;

    if (obavijestiBellDropdownOpen) {
        dropdown.classList.add('show');
        obavijestiBellLoadDataForDropdown();
    } else {
        dropdown.classList.remove('show');
    }
}

function obavijestiBellLoadDataForDropdown() {
    fetch(window.location.origin + '/seup/class/autocomplete.php?action=get_obavijesti')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                obavijestiBellUpdateDropdown(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading obavijesti:', error);
        });
}

function obavijestiBellMarkAsRead(obavijestId) {
    fetch(window.location.origin + '/seup/class/autocomplete.php?action=mark_obavijest_read&id=' + obavijestId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                obavijestiBellLoadData();

                const item = document.querySelector(`.obavijesti-item[data-id="${obavijestId}"]`);
                if (item) {
                    item.classList.remove('unread');
                }
            }
        })
        .catch(error => {
            console.error('Error marking obavijest as read:', error);
        });
}

function obavijestiBellMarkAllAsRead() {
    fetch(window.location.origin + '/seup/class/autocomplete.php?action=mark_all_obavijesti_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                obavijestiBellLoadData();
                obavijestiBellLoadDataForDropdown();
            }
        })
        .catch(error => {
            console.error('Error marking all obavijesti as read:', error);
        });
}

function obavijestiBellPlaySound() {
    const audio = document.getElementById('obavijesti-bell-sound');
    if (audio) {
        audio.play().catch(err => {
            console.log('Could not play sound:', err);
        });
    }
}

function obavijestiBellAttachListeners() {
    document.addEventListener('click', function(event) {
        const bellContainer = document.querySelector('.obavijesti-bell-container');
        const dropdown = document.querySelector('.obavijesti-dropdown');

        if (bellContainer && dropdown && obavijestiBellDropdownOpen) {
            if (!bellContainer.contains(event.target) && !dropdown.contains(event.target)) {
                obavijestiBellDropdownOpen = false;
                dropdown.classList.remove('show');
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.obavijesti-bell-container')) {
        obavijestiBellInit();
    }
});
