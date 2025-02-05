import Logo from "../ui/logo";

function Footer() {
  return (
    <footer className="bg-black">
      <div className="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <div className="flex justify-center ">
          <Logo />
        </div>

        <p className="mx-auto mt-6 max-w-md text-center leading-relaxed text-gray-500">
          Spreetzitt è una divisione di è una divisione di iFortech SRL
        </p>

        <p className="mx-auto mt-6 max-w-md text-center leading-relaxed text-gray-500 text-xs">
          VIA PISA 250 - SESTO SAN GIOVANNI - 20099 (MI)
        </p>
      </div>
    </footer>
  );
}

export default Footer;
