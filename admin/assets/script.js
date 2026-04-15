let selectedSeats = [];
let currentPricePerSeat = 0;

// Hàm mở Popup
function openPopup(id, maxSeats, occupiedCount, pricePerSeat) {
    const popup = document.getElementById("popup");
    const grid = document.getElementById("seat-grid");
    currentPricePerSeat = Number(pricePerSeat) || 0;
    if (currentPricePerSeat <= 0) {
        const btn = document.querySelector('[data-lich="' + String(id) + '"]');
        if (btn) {
            currentPricePerSeat = Number(btn.getAttribute("data-gia")) || 0;
        }
    }
    
    // Hiển thị popup dạng flex để căn giữa
    popup.style.display = "flex";
    document.getElementById("id_lich").value = id;
    
    // Reset dữ liệu cũ
    grid.innerHTML = ""; 
    selectedSeats = []; 
    updateUI();

    // Vòng lặp tạo ghế
    for (let i = 1; i <= maxSeats; i++) {
        const seat = document.createElement("div");
        seat.classList.add("seat"); // Class phải khớp với CSS
        
        // Kiểm tra nếu ghế đã bị chiếm (occupiedCount lấy từ database)
        if (i <= occupiedCount) {
            seat.classList.add("occupied");
            seat.innerText = "X";
        } else {
            seat.classList.add("available");
            seat.innerText = i;
            // Gán sự kiện click cho ghế trống
            seat.onclick = function() {
                toggleSeat(this, i);
            };
        }
        grid.appendChild(seat);
    }
}

// Hàm đóng Popup
function closePopup() {
    document.getElementById("popup").style.display = "none";
}

// Hàm chọn/hủy chọn ghế
function toggleSeat(seatElem, seatNum) {
    if (selectedSeats.includes(seatNum)) {
        // Nếu đã chọn rồi thì bỏ chọn
        selectedSeats = selectedSeats.filter(s => s !== seatNum);
        seatElem.classList.remove("selected");
    } else {
        // Nếu chưa chọn thì thêm vào danh sách
        selectedSeats.push(seatNum);
        seatElem.classList.add("selected");
    }
    updateUI();
}

// Cập nhật giao diện (Số ghế hiển thị và Tổng tiền)
function updateUI() {
    const displaySeats = document.getElementById("display-seats");
    const displayTotal = document.getElementById("display-total");
    const inputSeats = document.getElementById("selected_seats");

    if (selectedSeats.length > 0) {
        inputSeats.value = selectedSeats.join(",");
        displaySeats.innerText = selectedSeats.sort((a,b) => a-b).join(", ");
        displayTotal.innerText = (selectedSeats.length * currentPricePerSeat).toLocaleString() + "đ";
    } else {
        inputSeats.value = "";
        displaySeats.innerText = "Chưa chọn";
        displayTotal.innerText = "0đ";
    }
}

// Đóng popup khi click ra ngoài vùng trắng
window.onclick = function(event) {
    const popup = document.getElementById("popup");
    if (event.target == popup) {
        closePopup();
    }
}