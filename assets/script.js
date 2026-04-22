let selectedSeats = [];
let seatPrice = 0;
let bookedSeats = [];

/* MỞ POPUP */
function openPopup(maLich, totalSeats, price) {
    document.getElementById("popup").style.display = "flex";
    document.getElementById("id_lich").value = maLich;

    selectedSeats = [];
    bookedSeats = [];
    seatPrice = Number(price);

    fetch("layghe.php?ma_lich=" + encodeURIComponent(maLich))
        .then(res => res.json())
        .then(data => {
            bookedSeats = data.map(String);
            renderSeats(totalSeats);
            updateDisplay();
        })
        .catch(() => {
            bookedSeats = [];
            renderSeats(totalSeats);
            updateDisplay();
        });
}

/* ĐÓNG POPUP */
function closePopup() {
    document.getElementById("popup").style.display = "none";
}

/* VẼ GHẾ */
function renderSeats(totalSeats) {
    const grid = document.getElementById("seat-grid");
    grid.innerHTML = "";

    for (let i = 1; i <= totalSeats; i++) {
        const seat = document.createElement("div");
        seat.innerText = i;
        seat.classList.add("seat");

        if (bookedSeats.includes(String(i))) {
            seat.classList.add("occupied");
        } else {
            seat.classList.add("available");
            seat.onclick = () => toggleSeat(i, seat);
        }

        grid.appendChild(seat);
    }
}

/* CHỌN / BỎ CHỌN */
function toggleSeat(number, seatDiv) {
    if (selectedSeats.includes(number)) {
        selectedSeats = selectedSeats.filter(x => x !== number);
        seatDiv.classList.remove("selected");
        seatDiv.classList.add("available");
    } else {
        selectedSeats.push(number);
        seatDiv.classList.remove("available");
        seatDiv.classList.add("selected");
    }

    updateDisplay();
}

/* CẬP NHẬT */
function updateDisplay() {
    selectedSeats.sort((a, b) => a - b);

    document.getElementById("selected_seats").value =
        selectedSeats.join(",");

    document.getElementById("seatText").innerText =
        selectedSeats.length
            ? selectedSeats.join(", ")
            : "Chưa chọn";

    const radio = document.querySelector('input[name="hinh_thuc_tt"]:checked');

    let percent = radio ? Number(radio.value) : 100;

    let total = selectedSeats.length * seatPrice;
    let needPay = total * percent / 100;

    document.getElementById("tongTien").innerText =
        needPay.toLocaleString("vi-VN") + "đ";
}

/* RADIO THANH TOÁN */
document.addEventListener("change", function (e) {
    if (e.target.name === "hinh_thuc_tt") {
        updateDisplay();
    }
});

/* CLICK NGOÀI POPUP */
window.addEventListener("click", function (e) {
    const popup = document.getElementById("popup");
    if (e.target === popup) {
        closePopup();
    }
});