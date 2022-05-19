$( ".nope1" ).click(function() {
  $( ".nothing1" ).toggle( "slow", function() {
    // Animation complete.
  });
});

$( ".nope2" ).click(function() {
  $( ".nothing2" ).toggle( "slow", function() {
    // Animation complete.
  });
});

$( ".nope3" ).click(function() {
  $( ".nothing3" ).toggle( "slow", function() {
    // Animation complete.
  });
});

$( ".nope4" ).click(function() {
  $( ".nothing4" ).toggle( "slow", function() {
    // Animation complete.
  });
});

$( ".nope5" ).click(function() {
  $( ".nothing5" ).toggle( "slow", function() {
    // Animation complete.
  });
});

function myFunction1000() {
  var x = document.getElementById("myDIV");
  if (x.innerHTML === "") {
    x.innerHTML = "Swapped text!";
  } else {
    x.innerHTML = "";
  }
}
