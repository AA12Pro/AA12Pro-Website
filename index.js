
document.querySelector(".join-server button").addEventListener("click", myFunction);

function myFunction() {
  /* Get the text field */
  var copyText = document.getElementById("myInput");

  /* Select the text field */
  copyText.select();
  copyText.setSelectionRange(0, 99999); /* For mobile devices */

  /* Copy the text inside the text field */
  navigator.clipboard.writeText(copyText.value).style.visibility='hidden';

  /* Alert the copied text */

  animateWord();
}

function animateWord() {
  document.querySelector(".join-server button").innerHTML = "IP Address Copied";

  setTimeout(function () {
    document.querySelector(".join-server button").innerHTML = "Copy the IP Address";
  }, 1000);
}
