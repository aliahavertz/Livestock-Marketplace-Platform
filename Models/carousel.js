"use strict";

let next = document.querySelector(".next");
let prev = document.querySelector(".prev");
let slideContainer = document.querySelector(".container");

function moveNext() {
    let items = document.querySelectorAll(".item");
    document.querySelector(".slide").appendChild(items[0]);
}

function movePrev() {
    let items = document.querySelectorAll(".item");
    document.querySelector(".slide").prepend(items[items.length - 1]);
}

next.addEventListener("click", () => moveNext());
prev.addEventListener("click", () => movePrev());

// 5-Second Auto-Play
let autoPlay = setInterval(moveNext, 5000);

slideContainer.addEventListener("mouseenter", () => clearInterval(autoPlay));
slideContainer.addEventListener("mouseleave", () => autoPlay = setInterval(moveNext, 5000));