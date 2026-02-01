const slideContainer = document.querySelector(".carousel-slide");
  const slides = document.querySelectorAll(".slide");
  const nextBtn = document.querySelector(".next");
  const prevBtn = document.querySelector(".prev");

  let index = 0;
  let autoSlideInterval;

  function showSlide(i) {
    if (i < 0) index = slides.length - 1;
    else if (i >= slides.length) index = 0;
    else index = i;
    slideContainer.style.transform = `translateX(${-index * 100}%)`;
  }

  function nextSlide() {
    showSlide(index + 1);
    resetAutoSlide();
  }

  function prevSlide() {
    showSlide(index - 1);
    resetAutoSlide();
  }

  function startAutoSlide() {
    autoSlideInterval = setInterval(() => showSlide(index + 1), 6000);
  }

  function resetAutoSlide() {
    clearInterval(autoSlideInterval);
    startAutoSlide();
  }

  nextBtn.addEventListener("click", nextSlide);
  prevBtn.addEventListener("click", prevSlide);

  // Lancer le carrousel automatiquement
  startAutoSlide();